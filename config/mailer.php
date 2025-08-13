<?php
/**
 * Mail helper: prefers PHPMailer with SMTP using company settings; falls back to PHP mail() ONLY when no SMTP host is configured.
 */
function sendCompanyEmail(PDO $conn, int $companyId, string $toEmail, string $subject, string $htmlBody, ?string $plainBody = null, ?string &$error = null): bool {
    // Try PHPMailer via Composer autoload
    $usePhpMailer = false;
    $autoloadPaths = [__DIR__ . '/../vendor/autoload.php', __DIR__ . '/vendor/autoload.php'];
    foreach ($autoloadPaths as $ap) {
        if (file_exists($ap)) { require_once $ap; $usePhpMailer = true; break; }
    }

    // Fetch company settings
    $stmt = $conn->prepare("SELECT setting_key, setting_value FROM company_settings WHERE company_id = ? AND setting_key IN ('smtp_username','smtp_host','smtp_port','smtp_password','smtp_encryption','company_name','company_email')");
    $stmt->execute([$companyId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $settings = [];
    foreach ($rows as $r) { $settings[$r['setting_key']] = $r['setting_value']; }

    $fromEmail = $settings['company_email'] ?? ($settings['smtp_username'] ?? 'no-reply@example.com');
    $fromName = $settings['company_name'] ?? 'System';
    $hasSmtp = !empty($settings['smtp_host']);

    if ($hasSmtp) {
        if (!$usePhpMailer || !class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            $error = 'PHPMailer not installed. Install dependencies (composer install).';
            return false;
        }
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();
            $mail->Host = $settings['smtp_host'];
            $mail->SMTPAuth = !empty($settings['smtp_username']);
            if ($mail->SMTPAuth) {
                $mail->Username = $settings['smtp_username'];
                $mail->Password = $settings['smtp_password'] ?? '';
            }
            $enc = strtolower($settings['smtp_encryption'] ?? 'tls');
            if ($enc === 'ssl') { $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS; }
            elseif ($enc === 'tls') { $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; }
            $mail->Port = (int)($settings['smtp_port'] ?? 587);
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($toEmail);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $plainBody ?? strip_tags($htmlBody);
            $mail->send();
            return true;
        } catch (Throwable $t) {
            $error = $t->getMessage();
            return false;
        }
    }

    // Fallback: PHP mail() (only when no SMTP configured)
    try {
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