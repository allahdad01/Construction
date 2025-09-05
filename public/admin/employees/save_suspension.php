<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

// Check if user is authenticated and has appropriate role
requireAuth();
requireAnyRole(['company_admin', 'super_admin']);

$response = ['success' => false, 'message' => 'Invalid request'];

try {
    $db = new Database();
    $conn = $db->getConnection();
    $company_id = getCurrentCompanyId();

    // Validate request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method");
    }

    // Validate action
    $action = $_POST['action'] ?? '';
    if ($action !== 'save_suspension') {
        throw new Exception("Invalid action");
    }

    // Validate employee ID
    $employee_id = (int)($_POST['employee_id'] ?? 0);
    if ($employee_id <= 0) {
        throw new Exception("Invalid employee ID");
    }

    // Validate required fields
    if (empty($_POST['suspension_start_date'])) {
        throw new Exception("Suspension start date is required");
    }

    // Validate end date if provided
    $suspension_start = new DateTime($_POST['suspension_start_date']);
    $suspension_end = !empty($_POST['suspension_end_date']) ? new DateTime($_POST['suspension_end_date']) : null;
    
    if ($suspension_end && $suspension_start > $suspension_end) {
        throw new Exception("Suspension end date must be after the start date");
    }

    // Prepare suspension data
    $suspension_data = [
        'employee_id' => $employee_id,
        'company_id' => $company_id,
        'suspension_start_date' => $_POST['suspension_start_date'],
        'suspension_end_date' => $suspension_end ? $suspension_end->format('Y-m-d') : null,
        'reason' => $_POST['suspension_reason'] ?? null,
        'status' => 'active' // Always set to active when saving
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
    $response = ['success' => true, 'message' => 'Suspension saved successfully'];

} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    // Return error response
    $response = ['success' => false, 'message' => $e->getMessage()];
} finally {
    // Send JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>