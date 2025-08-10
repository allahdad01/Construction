<?php
/**
 * Lightweight mail helper. Uses PHP mail() with headers.
 * Reads SMTP-like settings from company_settings for From address and naming, but does not open SMTP sockets.
 * Returns true/false; sets $error by reference on failure.
 */
function sendCompanyEmail(PDO $conn, int $companyId, string $toEmail, string $subject, string $htmlBody, ?string $plainBody = null, ?string &$error = null): bool {
    try {
        // Fetch company settings for sender details
        $stmt = $conn->prepare("SELECT setting_key, setting_value FROM company_settings WHERE company_id = ? AND setting_key IN ('smtp_username','smtp_host','company_name','company_email')");
        $stmt->execute([$companyId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $settings = [];
        foreach ($rows as $r) { $settings[$r['setting_key']] = $r['setting_value']; }

        // Determine From header
        $fromEmail = $settings['company_email'] ?? ($settings['smtp_username'] ?? 'no-reply@example.com');
        $fromName = $settings['company_name'] ?? 'System';
        $boundary = md5(uniqid((string)mt_rand(), true));

        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'From: ' . sprintf('%s <%s>', addslashes($fromName), $fromEmail);
        $headers[] = 'Reply-To: ' . $fromEmail;
        $headers[] = 'X-Mailer: PHP/' . phpversion();
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

        $plain = $plainBody ?? strip_tags(preg_replace('/<br\s*\/?>(\r?\n)?/i', "\n", $htmlBody));

        $message = '';
        $message .= '--' . $boundary . "\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $message .= $plain . "\r\n\r\n";

        $message .= '--' . $boundary . "\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $message .= $htmlBody . "\r\n\r\n";

        $message .= '--' . $boundary . "--\r\n";

        $ok = @mail($toEmail, '=?UTF-8?B?' . base64_encode($subject) . '?=', $message, implode("\r\n", $headers));
        if (!$ok) { $error = 'mail() returned false'; }
        return (bool)$ok;
    } catch (Throwable $t) {
        $error = $t->getMessage();
        return false;
    }
}