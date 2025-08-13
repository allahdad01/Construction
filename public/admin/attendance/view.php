<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

// Check if user is authenticated and has appropriate role
requireAuth();
requireAnyRole(['company_admin', 'super_admin']);
require_once '../../../includes/header.php';

$db = new Database();
$conn = $db->getConnection();
$company_id = getCurrentCompanyId();

$error = '';
$success = '';

// Get attendance ID from URL
$attendance_id = $_GET['id'] ?? null;

if (!$attendance_id) {
    header('Location: index.php');
    exit;
}

// Get attendance details with employee information
$stmt = $conn->prepare("
    SELECT ea.*, 
           e.employee_code,
           e.name as employee_name,
           e.position
    FROM employee_attendance ea 
    LEFT JOIN employees e ON ea.employee_id = e.id
    WHERE ea.id = ? AND ea.company_id = ?
");
$stmt->execute([$attendance_id, $company_id]);
$attendance = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attendance) {
    header('Location: index.php');
    exit;
}

// Calculate metrics
$total_hours = $attendance['working_hours'] ?? 0;
$date_obj = new DateTime($attendance['date']);
$day_of_week = $date_obj->format('l');
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-clock"></i> <?php echo __('attendance_details'); ?>
        </h1>
        <div>
            <a href="edit.php?id=<?php echo $attendance_id; ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> <?php echo __('edit_attendance'); ?>
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> <?php echo __('back_to_attendance'); ?>
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Attendance Details -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('attendance_information'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong><?php echo __('employee'); ?>:</strong> <?php echo htmlspecialchars($attendance['employee_name']); ?></p>
                            <p><strong><?php echo __('employee_code'); ?>:</strong> <?php echo htmlspecialchars($attendance['employee_code']); ?></p>
                            <p><strong><?php echo __('position'); ?>:</strong> <?php echo htmlspecialchars($attendance['position']); ?></p>
                            <p><strong><?php echo __('date'); ?>:</strong> <?php echo date('M j, Y', strtotime($attendance['date'])); ?></p>
                            <p><strong><?php echo __('day_of_week'); ?>:</strong> <?php echo $day_of_week; ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong><?php echo __('status'); ?>:</strong> 
                                <span class="badge badge-<?php echo $attendance['status'] == 'present' ? 'success' : ($attendance['status'] == 'absent' ? 'danger' : 'warning'); ?>">
                                    <?php echo ucfirst($attendance['status']); ?>
                                </span>
                            </p>
                            <?php if ($attendance['check_in_time']): ?>
                            <p><strong><?php echo __('check_in_time'); ?>:</strong> <?php echo date('H:i', strtotime($attendance['check_in_time'])); ?></p>
                            <?php endif; ?>
                            <?php if ($attendance['check_out_time']): ?>
                            <p><strong><?php echo __('check_out_time'); ?>:</strong> <?php echo date('H:i', strtotime($attendance['check_out_time'])); ?></p>
                            <?php endif; ?>
                            <?php if ($attendance['working_hours']): ?>
                            <p><strong><?php echo __('working_hours'); ?>:</strong> <?php echo $attendance['working_hours']; ?> hours</p>
                            <?php endif; ?>
                            <?php if ($attendance['leave_type']): ?>
                            <p><strong><?php echo __('leave_type'); ?>:</strong> <?php echo ucfirst($attendance['leave_type']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($attendance['notes']): ?>
                    <div class="row mt-3">
                        <div class="col-12">
                            <p><strong><?php echo __('notes'); ?>:</strong></p>
                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($attendance['notes'])); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Time Summary -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('time_summary'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="text-primary"><?php echo __('total_hours'); ?></h6>
                        <h4 class="text-success"><?php echo number_format($total_hours, 2); ?> hrs</h4>
                    </div>
                    
                    <?php if ($attendance['check_in_time'] && $attendance['check_out_time']): ?>
                    <div class="mb-3">
                        <h6 class="text-primary"><?php echo __('work_duration'); ?></h6>
                        <p class="mb-1"><?php echo date('H:i', strtotime($attendance['check_in_time'])); ?> - <?php echo date('H:i', strtotime($attendance['check_out_time'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <h6 class="text-primary"><?php echo __('attendance_status'); ?></h6>
                        <span class="badge badge-<?php echo $attendance['status'] == 'present' ? 'success' : ($attendance['status'] == 'absent' ? 'danger' : 'warning'); ?> badge-lg">
                            <?php echo ucfirst($attendance['status']); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Attendance Timeline -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('timeline'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-marker bg-success"></div>
                            <div class="timeline-content">
                                <h6 class="timeline-title"><?php echo __('record_created'); ?></h6>
                                <p class="timeline-text"><?php echo formatDateTime($attendance['created_at']); ?></p>
                            </div>
                        </div>
                        
                        <?php if ($attendance['check_in_time']): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker bg-info"></div>
                            <div class="timeline-content">
                                <h6 class="timeline-title"><?php echo __('checked_in'); ?></h6>
                                <p class="timeline-text"><?php echo date('M j, Y H:i', strtotime($attendance['date'] . ' ' . $attendance['check_in_time'])); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($attendance['check_out_time']): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker bg-primary"></div>
                            <div class="timeline-content">
                                <h6 class="timeline-title"><?php echo __('checked_out'); ?></h6>
                                <p class="timeline-text"><?php echo date('M j, Y H:i', strtotime($attendance['date'] . ' ' . $attendance['check_out_time'])); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($attendance['updated_at'] && $attendance['updated_at'] != $attendance['created_at']): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker bg-secondary"></div>
                            <div class="timeline-content">
                                <h6 class="timeline-title"><?php echo __('last_updated'); ?></h6>
                                <p class="timeline-text"><?php echo formatDateTime($attendance['updated_at']); ?></p>
                            </div>
                        </div>
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
                    <div class="d-grid gap-2">
                        <a href="edit.php?id=<?php echo $attendance_id; ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-edit"></i> <?php echo __('edit_record'); ?>
                        </a>
                        
                        <button class="btn btn-danger btn-sm" onclick="confirmDelete(<?php echo $attendance_id; ?>, '<?php echo htmlspecialchars($attendance['employee_name'] . ' - ' . date('M j, Y', strtotime($attendance['date']))); ?>')">
                            <i class="fas fa-trash"></i> <?php echo __('delete_record'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline:before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-marker {
    position: absolute;
    left: -22px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid #fff;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.timeline-content {
    background: #f8f9fa;
    padding: 10px 15px;
    border-radius: 5px;
    border-left: 3px solid #007bff;
}

.timeline-title {
    margin-bottom: 5px;
    font-weight: 600;
    color: #495057;
}

.timeline-text {
    margin: 0;
    color: #6c757d;
    font-size: 0.9em;
}

.badge-lg {
    font-size: 1em;
    padding: 0.5em 1em;
}
</style>

<script>
function confirmDelete(attendanceId, description) {
    const message = `Are you sure you want to delete attendance record for "${description}"? This action cannot be undone.`;
    if (confirm(message)) {
        window.location.href = `index.php?delete=${attendanceId}`;
    }
}
</script>

<?php require_once '../../../includes/footer.php'; ?>