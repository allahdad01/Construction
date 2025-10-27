<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

// Check if user is authenticated and has appropriate role
requireAuth();
requireAnyRole(['company_admin', 'super_admin']);

// Validate employee ID
$employee_id = (int)($_GET['employee_id'] ?? 0);
if (!$employee_id) {
    http_response_code(400);
    echo '<div class="alert alert-danger">Invalid employee ID</div>';
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    $company_id = getCurrentCompanyId();

    // Fetch employee details
    $stmt = $conn->prepare("SELECT name, employee_code FROM employees WHERE id = ? AND company_id = ?");
    $stmt->execute([$employee_id, $company_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        http_response_code(404);
        echo '<div class="alert alert-danger">Employee not found</div>';
        exit;
    }

    // Fetch existing work suspensions
    $stmt = $conn->prepare("
        SELECT * FROM employee_work_suspensions 
        WHERE employee_id = ? AND company_id = ?
        ORDER BY suspension_start_date DESC
    ");
    $stmt->execute([$employee_id, $company_id]);
    $work_suspensions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="row">
    <div class="col-lg-7">
        <h5 class="mb-4"><?php echo __('existing_suspensions'); ?></h5>
        <?php if (empty($work_suspensions)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> <?php echo __('no_suspensions_found'); ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th><?php echo __('start_date'); ?></th>
                            <th><?php echo __('end_date'); ?></th>
                            <th><?php echo __('reason'); ?></th>
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
                            <td><?php echo htmlspecialchars($suspension['reason'] ?? 'N/A'); ?></td>
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
    <div class="col-lg-5">
        <h5 class="mb-4"><?php echo __('add_new_suspension'); ?></h5>
        <form id="suspensionForm" method="POST" action="save_suspension.php">
            <input type="hidden" name="action" value="save_suspension">
            <input type="hidden" name="employee_id" value="<?php echo $employee_id; ?>">
            <input type="hidden" name="suspension_id" id="suspensionId">

            <div class="row">
                <div class="col-md-12">
                    <div class="mb-3">
                        <label for="suspension_start_date" class="form-label"><?php echo __('start_date'); ?> *</label>
                        <input type="date" class="form-control" id="suspension_start_date" name="suspension_start_date" required>
                    </div>
                </div>
                <div class="col-md-12">
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

        // Log form data for debugging
        console.log('Suspension Form Data:');
        for (let [key, value] of formData.entries()) {
            console.log(`${key}: ${value}`);
        }

        // Send AJAX request
        fetch('save_suspension.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Try to show toast in parent window
                if (window.parent && window.parent.showToast) {
                    window.parent.showToast('success', data.message);
                } else {
                    alert(data.message);
                }

                // Reload the modal content
                loadSuspensionDetails(<?php echo $employee_id; ?>);
                
                // Optional: Reset form
                suspensionForm.reset();
                ongoingSuspensionCheckbox.dispatchEvent(new Event('change'));
            } else {
                // Show error toast or alert
                if (window.parent && window.parent.showToast) {
                    window.parent.showToast('danger', data.message);
                } else {
                    alert(data.message);
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            
            // Show error toast or alert
            if (window.parent && window.parent.showToast) {
                window.parent.showToast('danger', '<?php echo __('network_error_try_again'); ?>');
            } else {
                alert('<?php echo __('network_error_try_again'); ?>');
            }
        });
    });
});
</script>
<?php
} catch (Exception $e) {
    http_response_code(500);
    echo '<div class="alert alert-danger">' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}
?>
