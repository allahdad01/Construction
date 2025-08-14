<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/mailer.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $email = trim($input['email'] ?? '');
    if ($email === '') {
        echo json_encode(['success' => false, 'message' => 'Email is required']);
        exit;
    }

    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare('SELECT id, company_id, email FROM users WHERE email = ? AND is_active = 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Always return success-ish to avoid enumeration
    $genericMsg = 'If the email exists, a reset link has been sent.';

    if (!$user) {
        echo json_encode(['success' => true, 'message' => $genericMsg]);
        exit;
    }

    // Create password reset token
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour

    // Ensure table exists
    $conn->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(128) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_token (token),
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Insert token
    $ins = $conn->prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)');
    $ins->execute([$user['id'], $token, $expires]);

    // Compose email
    $resetUrl = sprintf('%s://%s%sreset-password.php?token=%s',
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http',
        $_SERVER['HTTP_HOST'],
        $base_url,
        $token
    );
    $html = '<p>We received a request to reset your password.</p>' .
            '<p><a href="' . htmlspecialchars($resetUrl) . '">Click here to reset your password</a></p>' .
            '<p>This link will expire in 1 hour.</p>';

    $err = null;
    $sent = sendCompanyEmail($conn, (int)($user['company_id'] ?? 0), $user['email'], 'Password Reset', $html, null, $err);

    if (!$sent && $err) {
        // Log error but still return generic success
        error_log('Password reset email error: ' . $err);
    }

    echo json_encode(['success' => true, 'message' => $genericMsg]);
} catch (Throwable $t) {
    error_log('request-password-reset error: ' . $t->getMessage());
    echo json_encode(['success' => false, 'message' => 'Unexpected error']);
}