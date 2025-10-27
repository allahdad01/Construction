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
$redirect_after_save = '';

// Get employee ID from URL
$employee_id = (int)($_GET['id'] ?? 0);

if (!$employee_id) {
    header('Location: index.php');
    exit;
}

// Get employee details
$stmt = $conn->prepare("
    SELECT e.*, u.email as user_email, u.status as user_status
    FROM employees e
    LEFT JOIN users u ON e.user_id = u.id
    WHERE e.id = ? AND e.company_id = ?
");
$stmt->execute([$employee_id, $company_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    header('Location: index.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['name', 'position', 'monthly_salary'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Field '$field' is required.");
            }
        }

        // Validate salary
        if (!is_numeric($_POST['monthly_salary']) || $_POST['monthly_salary'] <= 0) {
            throw new Exception("Monthly salary must be a positive number.");
        }

        // Validate work suspension dates
        if (!empty($_POST['work_suspension_start']) && !empty($_POST['work_suspension_end'])) {
            $suspension_start = new DateTime($_POST['work_suspension_start']);
            $suspension_end = new DateTime($_POST['work_suspension_end']);
            
            if ($suspension_start > $suspension_end) {
                throw new Exception("Work suspension end date must be after the start date.");
            }
        }

        // Handle suspension submission
        if (isset($_POST['action']) && $_POST['action'] === 'save_suspension') {
            try {
                // Validate required fields
                if (empty($_POST['suspension_start_date'])) {
                    throw new Exception("Suspension start date is required.");
                }

                // Validate end date if provided
                $suspension_start = new DateTime($_POST['suspension_start_date']);
                $suspension_end = !empty($_POST['suspension_end_date']) ? new DateTime($_POST['suspension_end_date']) : null;
                
                if ($suspension_end && $suspension_start > $suspension_end) {
                    throw new Exception("Suspension end date must be after the start date.");
                }

                // Prepare suspension data
                $suspension_data = [
                    'employee_id' => $employee_id,
                    'company_id' => $company_id,
                    'suspension_start_date' => $_POST['suspension_start_date'],
                    'suspension_end_date' => $suspension_end ? $suspension_end->format('Y-m-d') : null,
                    'reason' => $_POST['suspension_reason'] ?? null,
                    'status' => $suspension_end ? 'ended' : 'active'
                ];

                // Start transaction
                $conn->beginTransaction();

                // Check if editing existing suspension
                if (!empty($_POST['suspension_id'])) {
                    $stmt = $conn->prepare("
                        UPDATE employee_work_suspensions 
                        SET suspension_start_date = ?, 
                            suspension_end_date = ?, 
                            reason = ?, 
                            status = ?, 
                            updated_at = NOW()
                        WHERE id = ? AND employee_id = ? AND company_id = ?
                    ");
                    $stmt->execute([
                        $suspension_data['suspension_start_date'],
                        $suspension_data['suspension_end_date'],
                        $suspension_data['reason'],
                        $suspension_data['status'],
                        $_POST['suspension_id'],
                        $employee_id,
                        $company_id
                    ]);
                } else {
                    // Insert new suspension
                    $stmt = $conn->prepare("
                        INSERT INTO employee_work_suspensions 
                        (employee_id, company_id, suspension_start_date, suspension_end_date, reason, status) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $employee_id,
                        $company_id,
                        $suspension_data['suspension_start_date'],
                        $suspension_data['suspension_end_date'],
                        $suspension_data['reason'],
                        $suspension_data['status']
                    ]);
                }

                // Commit transaction
                $conn->commit();

                // Return success response
                echo json_encode(['success' => true]);
                exit;

            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollBack();
                
                // Return error response
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
        }

        // Start transaction
        $conn->beginTransaction();

        // Update employee record
        $stmt = $conn->prepare("
            UPDATE employees SET
                name = ?,
                position = ?,
                email = ?,
                phone = ?,
                monthly_salary = ?,
                hire_date = ?,
                status = ?,
                updated_at = NOW()
            WHERE id = ? AND company_id = ?
        ");

        $stmt->execute([
            $_POST['name'],
            $_POST['position'],
            $_POST['email'] ?? '',
            $_POST['phone'] ?? '',
            $_POST['monthly_salary'],
            $_POST['hire_date'] ?? $employee['hire_date'],
            $_POST['status'] ?? 'active',
            $employee_id,
            $company_id
        ]);

        // Update user account if email changed
        if ($employee['user_id'] && !empty($_POST['email']) && $_POST['email'] !== $employee['email']) {
            // Check if new email already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$_POST['email'], $employee['user_id']]);
            if ($stmt->fetch()) {
                throw new Exception("Email already exists in the system.");
            }

            // Update user email and name
            $name_parts = explode(' ', trim($_POST['name']), 2);
            $first_name = $name_parts[0];
            $last_name = isset($name_parts[1]) ? $name_parts[1] : '';

            $stmt = $conn->prepare("UPDATE users SET email = ?, first_name = ?, last_name = ? WHERE id = ?");
            $stmt->execute([$_POST['email'], $first_name, $last_name, $employee['user_id']]);
        }

        // Commit transaction
        $conn->commit();

        $success = "Employee updated successfully!";
        $redirect_after_save = "view.php?id=$employee_id";

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $error = $e->getMessage();
    }
}

require_once '../../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-edit"></i> <?php echo __('edit_employee'); ?>
        </h1>
        <div class="d-flex">
            <a href="view.php?id=<?php echo $employee_id; ?>" class="btn btn-info me-2">
                <i class="fas fa-eye"></i> <?php echo __('view_employee'); ?>
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> <?php echo __('back_to_employees'); ?>
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo htmlspecialchars($success); ?>
        </div>
        <?php if (!empty($redirect_after_save)): ?>
            <script>setTimeout(function(){ window.location.href = '<?php echo $redirect_after_save; ?>'; }, 1500);</script>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Edit Employee Form -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('employee_information'); ?></h6>
                </div>
                <div class="card-body">
                    <form method="POST" id="employeeForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label"><?php echo __('full_name'); ?> *</label>
                                    <input type="text" class="form-control" id="name" name="name"
                                           value="<?php echo htmlspecialchars($employee['name']); ?>"
                                           required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label"><?php echo __('email_address'); ?></label>
                                    <input type="email" class="form-control" id="email" name="email"
                                           value="<?php echo htmlspecialchars($employee['email']); ?>">
                                    <div class="form-text"><?php echo __('this_will_update_the_user_account_email_if_changed'); ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label"><?php echo __('phone_number'); ?></label>
                                    <input type="tel" class="form-control" id="phone" name="phone"
                                           value="<?php echo htmlspecialchars($employee['phone']); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="position" class="form-label"><?php echo __('position'); ?> *</label>
                                    <select class="form-control" id="position" name="position" required>
                                        <option value=""><?php echo __('select_position'); ?></option>
                                        <option value="driver" <?php echo $employee['position'] === 'driver' ? 'selected' : ''; ?>><?php echo __('driver'); ?></option>
                                        <option value="driver_assistant" <?php echo $employee['position'] === 'driver_assistant' ? 'selected' : ''; ?>><?php echo __('driver_assistant'); ?></option>
                                        <option value="operator" <?php echo $employee['position'] === 'operator' ? 'selected' : ''; ?>><?php echo __('machine_operator'); ?></option>
                                        <option value="supervisor" <?php echo $employee['position'] === 'supervisor' ? 'selected' : ''; ?>><?php echo __('supervisor'); ?></option>
                                        <option value="technician" <?php echo $employee['position'] === 'technician' ? 'selected' : ''; ?>><?php echo __('technician'); ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="monthly_salary" class="form-label"><?php echo __('monthly_salary'); ?> *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="monthly_salary" name="monthly_salary"
                                               value="<?php echo htmlspecialchars($employee['monthly_salary']); ?>"
                                               step="0.01" min="0" required>
                                    </div>
                                    <div class="form-text"><?php echo __('daily_rate_will_be_calculated_automatically'); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="hire_date" class="form-label"><?php echo __('hire_date'); ?></label>
                                    <input type="date" class="form-control" id="hire_date" name="hire_date"
                                           value="<?php echo htmlspecialchars($employee['hire_date']); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="status" class="form-label"><?php echo __('status'); ?></label>
                                    <select class="form-control" id="status" name="status">
                                        <option value="active" <?php echo $employee['status'] === 'active' ? 'selected' : ''; ?>><?php echo __('active'); ?></option>
                                        <option value="inactive" <?php echo $employee['status'] === 'inactive' ? 'selected' : ''; ?>><?php echo __('inactive'); ?></option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label"><?php echo __('employee_code'); ?></label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($employee['employee_code']); ?>" readonly>
                                    <div class="form-text"><?php echo __('employee_code_cannot_be_changed'); ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label"><?php echo __('daily_rate'); ?></label>
                                    <input type="text" class="form-control" id="daily_rate_display"
                                           value="$<?php echo number_format($employee['daily_rate'], 2); ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label"><?php echo __('created_date'); ?></label>
                                    <input type="text" class="form-control"
                                           value="<?php echo date('M j, Y', strtotime($employee['created_at'])); ?>" readonly>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-between">
                            <a href="view.php?id=<?php echo $employee_id; ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> <?php echo __('cancel'); ?>
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> <?php echo __('update_employee'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Work Suspensions Section -->
            <div class="card shadow mb-4" id="work-suspensions">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-warning"><?php echo __('work_suspensions'); ?></h6>
                </div>
                <div class="card-body">
                    <div id="suspensionsContainer">
                                <?php
                        // Fetch existing work suspensions
                        $stmt = $conn->prepare("
                            SELECT * FROM employee_work_suspensions 
                            WHERE employee_id = ? AND company_id = ?
                            ORDER BY suspension_start_date DESC
                        ");
                        $stmt->execute([$employee_id, $company_id]);
                        $work_suspensions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>

                        <?php if (empty($work_suspensions)): ?>
                            <div class="text-center text-muted py-4" id="noSuspensionsMessage">
                                <i class="fas fa-pause-circle fa-3x mb-3"></i>
                                <p><?php echo __('no_work_suspensions_yet'); ?></p>
                        </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th><?php echo __('start_date'); ?></th>
                                            <th><?php echo __('end_date'); ?></th>
                                            <th><?php echo __('status'); ?></th>
                                            <th><?php echo __('actions'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($work_suspensions as $suspension): ?>
                                        <tr>
                                            <td><?php echo date('M j, Y', strtotime($suspension['suspension_start_date'])); ?></td>
                                            <td>
                                                <?php 
                                                echo $suspension['suspension_end_date'] 
                                                    ? date('M j, Y', strtotime($suspension['suspension_end_date'])) 
                                                    : '<span class="badge bg-warning">' . __('ongoing') . '</span>'; 
                                                ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $suspension['status'] === 'active' ? 'warning' : 'secondary'; 
                                                ?>">
                                                    <?php echo ucfirst($suspension['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary edit-suspension" 
                                                    data-id="<?php echo $suspension['id']; ?>"
                                                    data-start-date="<?php echo $suspension['suspension_start_date']; ?>"
                                                    data-end-date="<?php echo $suspension['suspension_end_date'] ?? ''; ?>"
                                                    data-reason="<?php echo htmlspecialchars($suspension['reason'] ?? ''); ?>"
                                                    data-status="<?php echo $suspension['status']; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                        </div>
                        <?php endif; ?>
                    </div>

                    <hr>

                    <div class="card-body">
                        <h6 class="mb-3"><?php echo __('add_new_suspension'); ?></h6>
                        <form id="suspensionForm">
                            <input type="hidden" name="action" value="save_suspension">
                            <input type="hidden" name="employee_id" value="<?php echo $employee_id; ?>">
                            <input type="hidden" name="suspension_id" id="suspensionId">

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="suspension_start_date" class="form-label"><?php echo __('start_date'); ?> *</label>
                                        <input type="date" class="form-control" id="suspension_start_date" name="suspension_start_date" required>
                    </div>
                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="suspension_end_date" class="form-label"><?php echo __('end_date'); ?></label>
                                        <input type="date" class="form-control" id="suspension_end_date" name="suspension_end_date">
                    </div>
                </div>
            </div>

                    <div class="mb-3">
                                <label for="suspension_reason" class="form-label"><?php echo __('reason'); ?></label>
                                <textarea class="form-control" id="suspension_reason" name="suspension_reason" rows="3" placeholder="<?php echo __('optional_suspension_reason'); ?>"></textarea>
                    </div>

                    <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="ongoing_suspension" name="ongoing_suspension">
                                    <label class="form-check-label" for="ongoing_suspension">
                                        <?php echo __('ongoing_suspension'); ?>
                                    </label>
                                </div>
                    </div>

                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> <?php echo __('save_suspension'); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const suspensionForm = document.getElementById('suspensionForm');
    const suspensionStartDate = document.getElementById('suspension_start_date');
    const suspensionEndDate = document.getElementById('suspension_end_date');
    const ongoingSuspensionCheckbox = document.getElementById('ongoing_suspension');
    const suspensionId = document.getElementById('suspensionId');
    const editSuspensionButtons = document.querySelectorAll('.edit-suspension');

    // Ongoing suspension checkbox logic
    ongoingSuspensionCheckbox.addEventListener('change', function() {
        suspensionEndDate.disabled = this.checked;
        if (this.checked) {
            suspensionEndDate.value = '';
        }
    });

    // Edit suspension buttons
    editSuspensionButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.dataset.id;
            const startDate = this.dataset.startDate;
            const endDate = this.dataset.endDate;
            const reason = this.dataset.reason;
            const status = this.dataset.status;

            // Populate form
            suspensionId.value = id;
            suspensionStartDate.value = startDate;
            suspensionEndDate.value = endDate;
            document.getElementById('suspension_reason').value = reason;
            ongoingSuspensionCheckbox.checked = !endDate;
            suspensionEndDate.disabled = !endDate;
        });
    });

    // Form submission
    suspensionForm.addEventListener('submit', function(e) {
        e.preventDefault();

        // Validate start date
        if (!suspensionStartDate.value) {
            alert('<?php echo __('start_date_is_required'); ?>');
            return;
        }

        // Validate end date if not ongoing
        if (!ongoingSuspensionCheckbox.checked && !suspensionEndDate.value) {
            alert('<?php echo __('end_date_is_required_or_mark_as_ongoing'); ?>');
            return;
        }

        // Validate end date is after start date
        if (!ongoingSuspensionCheckbox.checked && new Date(suspensionEndDate.value) < new Date(suspensionStartDate.value)) {
            alert('<?php echo __('end_date_must_be_after_start_date'); ?>');
            return;
        }

        // Prepare form data
        const formData = new FormData(suspensionForm);

        // Send AJAX request
        fetch('save_suspension.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Reload the page to show updated suspensions
                window.location.reload();
            } else {
                alert(data.message || '<?php echo __('error_saving_suspension'); ?>');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('<?php echo __('network_error_try_again'); ?>');
        });
    });
});
</script>

<?php require_once '../../../includes/footer.php'; ?>

                                </div>

                            </div>

                            <div class="col-md-6">

                                <div class="mb-3">

                                    <label class="form-label"><?php echo __('created_date'); ?></label>

                                    <input type="text" class="form-control"

                                           value="<?php echo date('M j, Y', strtotime($employee['created_at'])); ?>" readonly>

                                </div>

                            </div>

                        </div>



                        <hr>



                        <div class="d-flex justify-content-between">

                            <a href="view.php?id=<?php echo $employee_id; ?>" class="btn btn-secondary">

                                <i class="fas fa-times"></i> <?php echo __('cancel'); ?>

                            </a>

                            <button type="submit" class="btn btn-primary">

                                <i class="fas fa-save"></i> <?php echo __('update_employee'); ?>

                            </button>

                        </div>

                    </form>

                </div>

            </div>

        </div>



        <div class="col-lg-4">

            <!-- Employee Summary -->

            <div class="card shadow mb-4">

                <div class="card-header py-3">

                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('employee_summary'); ?></h6>

                </div>

                <div class="card-body">

                    <div class="text-center mb-3">

                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center mx-auto" style="width: 80px; height: 80px;">

                            <span class="text-white font-weight-bold" style="font-size: 1.5rem;">

                                <?php

                                $name_parts = explode(' ', $employee['name']);

                                echo strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));

                                ?>

                            </span>

                        </div>

                        <h5 class="mt-2"><?php echo htmlspecialchars($employee['name']); ?></h5>

                        <p class="text-muted"><?php echo htmlspecialchars($employee['employee_code']); ?></p>

                    </div>



                    <div class="row text-center">

                        <div class="col-6">

                            <h6 class="text-success">$<?php echo number_format($employee['monthly_salary'], 2); ?></h6>

                            <small class="text-muted"><?php echo __('monthly_salary'); ?></small>

                        </div>

                        <div class="col-6">

                            <h6 class="text-info">$<?php echo number_format($employee['daily_rate'], 2); ?></h6>

                            <small class="text-muted"><?php echo __('daily_rate'); ?></small>

                        </div>

                    </div>



                    <hr>



                    <div class="mb-2">

                        <strong><?php echo __('position'); ?>:</strong> <?php echo ucfirst(str_replace('_', ' ', $employee['position'])); ?>

                    </div>

                    <div class="mb-2">

                        <strong><?php echo __('status'); ?>:</strong>

                        <span class="badge bg-<?php echo $employee['status'] === 'active' ? 'success' : 'secondary'; ?>">

                            <?php echo ucfirst($employee['status']); ?>

                        </span>

                    </div>

                    <div class="mb-2">

                        <strong><?php echo __('user_account'); ?>:</strong>

                        <?php if ($employee['user_id']): ?>

                            <span class="badge bg-success"><?php echo __('active'); ?></span>

                        <?php else: ?>

                            <span class="badge bg-secondary"><?php echo __('no_account'); ?></span>

                        <?php endif; ?>

                    </div>

                </div>

            </div>



            <!-- Quick Actions -->

            <div class="card shadow mb-4">

                <div class="card-header py-3">

                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('quick_actions'); ?></h6>

                </div>

                <div class="card-body">

                    <div class="list-group list-group-flush">

                        <a href="view.php?id=<?php echo $employee_id; ?>" class="list-group-item list-group-item-action">

                            <i class="fas fa-eye me-2"></i> <?php echo __('view_employee_details'); ?>

                        </a>

                        <a href="../attendance/?employee_id=<?php echo $employee_id; ?>" class="list-group-item list-group-item-action">

                            <i class="fas fa-clock me-2"></i> <?php echo __('view_attendance'); ?>

                        </a>

                        <a href="../salary-payments/?employee_id=<?php echo $employee_id; ?>" class="list-group-item list-group-item-action">

                            <i class="fas fa-money-bill me-2"></i> <?php echo __('salary_payments'); ?>

                        </a>

                        <a href="../contracts/?employee_id=<?php echo $employee_id; ?>" class="list-group-item list-group-item-action">

                            <i class="fas fa-file-contract me-2"></i> <?php echo __('view_contracts'); ?>

                        </a>

                    </div>

                </div>

            </div>



            <!-- Information -->

            <div class="card shadow mb-4">

                <div class="card-header py-3">

                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('information'); ?></h6>

                </div>

                <div class="card-body">

                    <div class="mb-3">

                        <h6><i class="fas fa-info-circle text-info"></i> <?php echo __('employee_code'); ?></h6>

                        <p class="text-muted"><?php echo __('employee_codes_are_automatically_generated_and_cannot_be_changed'); ?></p>

                    </div>



                    <div class="mb-3">

                        <h6><i class="fas fa-calculator text-success"></i> <?php echo __('salary_calculation'); ?></h6>

                        <p class="text-muted"><?php echo __('daily_rate_is_calculated_as'); ?></p>

                    </div>



                    <div class="mb-3">

                        <h6><i class="fas fa-user-tag text-primary"></i> <?php echo __('positions'); ?></h6>

                        <ul class="text-muted">

                            <li><strong><?php echo __('driver'); ?>:</strong> <?php echo __('primary_machine_operator'); ?></li>

                            <li><strong><?php echo __('driver_assistant'); ?>:</strong> <?php echo __('supports_driver_operations'); ?></li>

                            <li><strong><?php echo __('machine_operator'); ?>:</strong> <?php echo __('specialized_machinery_operator'); ?></li>

                            <li><strong><?php echo __('supervisor'); ?>:</strong> <?php echo __('oversees_operations'); ?></li>

                            <li><strong><?php echo __('technician'); ?>:</strong> <?php echo __('maintains_equipment'); ?></li>

                        </ul>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>



<script>

document.addEventListener('DOMContentLoaded', function() {

    const form = document.getElementById('employeeForm');

    const monthlySalaryInput = document.getElementById('monthly_salary');

    const dailyRateDisplay = document.getElementById('daily_rate_display');



    // Real-time salary calculation

    monthlySalaryInput.addEventListener('input', function() {

        const salary = parseFloat(this.value) || 0;

        const dailyRate = salary / 30;

        dailyRateDisplay.value = '$' + dailyRate.toFixed(2);

    });



    // Form validation

    form.addEventListener('submit', function(e) {

        let isValid = true;

        const requiredFields = form.querySelectorAll('[required]');



        requiredFields.forEach(field => {

            if (!field.value.trim()) {

                isValid = false;

                field.classList.add('is-invalid');



                // Add error message if not exists

                if (!field.nextElementSibling || !field.nextElementSibling.classList.contains('invalid-feedback')) {

                    const errorDiv = document.createElement('div');

                    errorDiv.className = 'invalid-feedback';

                    errorDiv.textContent = 'This field is required.';

                    field.parentNode.appendChild(errorDiv);

                }

            } else {

                field.classList.remove('is-invalid');

                const errorDiv = field.parentNode.querySelector('.invalid-feedback');

                if (errorDiv) {

                    errorDiv.remove();

                }

            }

        });



        // Salary validation

        const salary = parseFloat(monthlySalaryInput.value);

        if (monthlySalaryInput.value && (isNaN(salary) || salary <= 0)) {

            isValid = false;

            monthlySalaryInput.classList.add('is-invalid');



            if (!monthlySalaryInput.nextElementSibling || !monthlySalaryInput.nextElementSibling.classList.contains('invalid-feedback')) {

                const errorDiv = document.createElement('div');

                errorDiv.className = 'invalid-feedback';

                errorDiv.textContent = 'Salary must be a positive number.';

                monthlySalaryInput.parentNode.appendChild(errorDiv);

            }

        }



        if (!isValid) {

            e.preventDefault();

            showNotification('Please fix the errors in the form.', 'error');

        }

    });



    // Real-time validation

    const inputs = form.querySelectorAll('input, select');

    inputs.forEach(input => {

        input.addEventListener('blur', function() {

            validateField(this);

        });



        input.addEventListener('input', function() {

            if (this.classList.contains('is-invalid')) {

                validateField(this);

            }

        });

    });



    function validateField(field) {

        const value = field.value.trim();

        let isValid = true;

        let errorMessage = '';



        // Remove existing error styling

        field.classList.remove('is-invalid');

        const existingError = field.parentNode.querySelector('.invalid-feedback');

        if (existingError) {

            existingError.remove();

        }



        // Required field validation

        if (field.hasAttribute('required') && !value) {

            isValid = false;

            errorMessage = 'This field is required.';

        }



        // Salary validation

        if (field.name === 'monthly_salary' && value) {

            const salary = parseFloat(value);

            if (isNaN(salary) || salary <= 0) {

                isValid = false;

                errorMessage = 'Salary must be a positive number.';

            }

        }



        // Apply validation result

        if (!isValid) {

            field.classList.add('is-invalid');

            const errorDiv = document.createElement('div');

            errorDiv.className = 'invalid-feedback';

            errorDiv.textContent = errorMessage;

            field.parentNode.appendChild(errorDiv);

        }

    }

});

</script>



<?php require_once '../../../includes/footer.php'; ?>
