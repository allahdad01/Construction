<?php
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is authenticated
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

$user_id = $_SESSION['user_id'];
$notification_id = $_POST['notification_id'] ?? null;
$action = $_POST['action'] ?? '';

try {
    if ($action === 'mark_all_read') {
        // Mark all notifications as read for this user
        $stmt = $conn->prepare("UPDATE user_notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'All notifications marked as read',
            'unread_count' => 0
        ]);
    } elseif ($action === 'mark_read' && $notification_id) {
        // Mark specific notification as read
        $stmt = $conn->prepare("UPDATE user_notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notification_id, $user_id]);
        
        // Get updated unread count
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Notification marked as read',
            'unread_count' => $result['count']
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid action'
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error processing request: ' . $e->getMessage()
    ]);
}
?>