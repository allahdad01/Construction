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

// Optional WebPush integration
function pushSendToUser(PDO $conn, int $userId, string $title, string $body): void {
    // Autoload if available
    $autoloadPaths = [__DIR__ . '/../vendor/autoload.php', __DIR__ . '/vendor/autoload.php'];
    $loaded = false;
    foreach ($autoloadPaths as $ap) { if (file_exists($ap)) { require_once $ap; $loaded = true; break; } }
    if (!$loaded) { return; }
    require_once __DIR__ . '/webpush.php';
    try {
        $keys = getVapidKeys($conn);
        $conn->exec("CREATE TABLE IF NOT EXISTS push_subscriptions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            company_id INT NOT NULL,
            endpoint TEXT NOT NULL,
            p256dh VARCHAR(200) NOT NULL,
            auth VARCHAR(100) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_endpoint (endpoint(255))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $stmt = $conn->prepare('SELECT endpoint, p256dh, auth FROM push_subscriptions WHERE user_id = ?');
        $stmt->execute([$userId]);
        $subs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (empty($subs)) { return; }
        $webPush = new \Minishlink\WebPush\WebPush([
            'VAPID' => [
                'subject' => $keys['subject'],
                'publicKey' => $keys['public'],
                'privateKey' => $keys['private'],
            ],
        ]);
        foreach ($subs as $s) {
            $subscription = \Minishlink\WebPush\Subscription::create([
                'endpoint' => $s['endpoint'],
                'publicKey' => $s['p256dh'],
                'authToken' => $s['auth'],
            ]);
            $webPush->queueNotification($subscription, json_encode(['title'=>$title,'body'=>$body]));
        }
        foreach ($webPush->flush() as $report) {
            if (!$report->isSuccess()) {
                $statusCode = $report->getResponse()?->getStatusCode();
                if ($statusCode === 404 || $statusCode === 410) {
                    // Remove stale subscription by endpoint
                    $endpoint = $report->getRequest()->getUri()->__toString();
                    $del = $conn->prepare('DELETE FROM push_subscriptions WHERE endpoint = ?');
                    $del->execute([$endpoint]);
                }
            }
        }
    } catch (\Throwable $e) {
        // Silent fail
    }
}

if (!function_exists('notifyUser')) {
    function notifyUser(PDO $conn, int $userId, string $title, string $message, string $type = 'info'): void {
        ensureNotificationsTable($conn);
        $stmt = $conn->prepare('INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())');
        $stmt->execute([$userId, $title, $message, $type]);
        // Push
        pushSendToUser($conn, $userId, $title, $message);
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
            $uid = (int)$uid;
            $ins->execute([$uid, $title, $message, $type]);
            pushSendToUser($conn, $uid, $title, $message);
        }
    }
}