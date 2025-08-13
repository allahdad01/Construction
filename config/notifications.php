<?php
function ensureNotificationsTable(PDO $conn): void {
    $conn->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        type VARCHAR(50) DEFAULT 'info',
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_is_read (is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

if (!function_exists('notifyUser')) {
    function notifyUser(PDO $conn, int $userId, string $title, string $message, string $type = 'info'): void {
        ensureNotificationsTable($conn);
        $stmt = $conn->prepare('INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())');
        $stmt->execute([$userId, $title, $message, $type]);
    }
}

if (!function_exists('notifyCompanyUsers')) {
    function notifyCompanyUsers(PDO $conn, int $companyId, string $title, string $message, string $type = 'info'): void {
        ensureNotificationsTable($conn);
        // Only tenant admins should receive notifications
        $usersStmt = $conn->prepare("SELECT id FROM users WHERE company_id = ? AND is_active = 1 AND role = 'company_admin'");
        $usersStmt->execute([$companyId]);
        $userIds = $usersStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        if (empty($userIds)) { return; }
        $ins = $conn->prepare('INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())');
        foreach ($userIds as $uid) {
            $ins->execute([(int)$uid, $title, $message, $type]);
        }
    }
}