<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Check if user is authenticated and has appropriate role
requireAuth();
requireAnyRole(['company_admin', 'super_admin']);

try {
    $db = new Database();
    $conn = $db->getConnection();
    $company_id = getCurrentCompanyId();

    // Start transaction
    $conn->beginTransaction();

    // Find active suspensions that have ended
    $stmt = $conn->prepare("
        SELECT DISTINCT 
            e.id AS employee_id, 
            e.status AS current_status,
            s.suspension_start_date,
            s.suspension_end_date,
            s.status AS suspension_status
        FROM employees e
        JOIN employee_work_suspensions s ON e.id = s.employee_id
        WHERE e.company_id = ? 
        AND (
            (e.status = 'inactive' AND s.suspension_end_date IS NOT NULL AND s.suspension_end_date < CURDATE())
            OR 
            (e.status = 'active' AND s.suspension_start_date <= CURDATE() AND s.suspension_end_date IS NOT NULL AND s.suspension_end_date < CURDATE())
        )
    ");
    $stmt->execute([$company_id]);
    $employees_to_update = $stmt->fetchAll(PDO::FETCH_ASSOC);



    // Reactivate or deactivate employees
    $update_employee_stmt = $conn->prepare("
        UPDATE employees 
        SET status = CASE 
            WHEN ? = 'reactivate' THEN 'active'
            WHEN ? = 'deactivate' THEN 'inactive'
        END,
        updated_at = NOW()
        WHERE id = ? AND company_id = ?
    ");

    // Update suspension status
    $update_suspension_stmt = $conn->prepare("
        UPDATE employee_work_suspensions 
        SET status = CASE 
            WHEN suspension_end_date IS NOT NULL AND suspension_end_date < CURDATE() THEN 'ended'
            ELSE status 
        END,
        updated_at = NOW()
        WHERE employee_id = ? 
        AND company_id = ? 
        AND status = 'active'
    ");

    $reactivated_count = 0;
    $deactivated_count = 0;
    foreach ($employees_to_update as $employee) {


        if ($employee['current_status'] === 'inactive' && $employee['suspension_end_date'] < date('Y-m-d')) {
            // Reactivate employee if suspension has ended
            $update_employee_stmt->execute(['reactivate', 'reactivate', $employee['employee_id'], $company_id]);
            $reactivated_count++;
        } elseif ($employee['current_status'] === 'active' && $employee['suspension_start_date'] <= date('Y-m-d') && $employee['suspension_end_date'] >= date('Y-m-d')) {
            // Deactivate employee if suspension is ongoing
            $update_employee_stmt->execute(['deactivate', 'deactivate', $employee['employee_id'], $company_id]);
            $deactivated_count++;
        }

        // Update suspension status
        $update_suspension_stmt->execute([$employee['employee_id'], $company_id]);
    }

    // Commit transaction
    $conn->commit();

    // Prepare response
    $response = [
        'success' => true, 
        'message' => "Automatically updated $reactivated_count employees to active and $deactivated_count to inactive.",
        'reactivated_employees' => $employees_to_update
    ];

} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    // Prepare error response
    $response = [
        'success' => false, 
        'message' => 'Error updating employee statuses: ' . $e->getMessage()
    ];
} finally {
    // Ensure JSON response for AJAX requests
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        // If no response was set, create a default one
        if (!isset($response)) {
            $response = [
                'success' => true,
                'message' => 'No employee status updates needed.',
                'reactivated_employees' => []
            ];
        }
        
        // Ensure JSON content type
        header('Content-Type: application/json');
        
        // Attempt to encode and output JSON, with error handling
        $json_response = json_encode($response);
        if ($json_response === false) {
            // Fallback response if JSON encoding fails
            $json_response = json_encode([
                'success' => false,
                'message' => 'Error encoding response: ' . json_last_error_msg()
            ]);
        }
        
        echo $json_response;
        exit;
    }
}
// If not an AJAX call, display results
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    // Only render HTML if it's not an AJAX request
    if (isset($response)) {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Employee Status Update</title>
            <link href="../../../assets/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
            <link href="../../../assets/css/sb-admin-2.min.css" rel="stylesheet">
        </head>
        <body>
            <div class="container-fluid">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-sync"></i> Employee Status Update
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if ($response['success']): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($response['message']); ?>
                            </div>
                            
                            <?php if (!empty($response['reactivated_employees'])): ?>
                                <h5>Reactivated Employees:</h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Employee ID</th>
                                                <th>Previous Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($response['reactivated_employees'] as $employee): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($employee['employee_id']); ?></td>
                                                <td><?php echo htmlspecialchars($employee['current_status']); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p>No employees needed reactivation.</p>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($response['message']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php 
    }
}
?>

