<?php
/**
 * VAPID key management helpers
 */

if (!function_exists('getSystemSettingLocal')) {
    function getSystemSettingLocal(PDO $conn, string $key, $default = '') {
        $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['setting_value'] ?? $default;
    }
}

function ensureSystemSettingsTable(PDO $conn): void {
    $conn->exec("CREATE TABLE IF NOT EXISTS system_settings (
        setting_key VARCHAR(191) NOT NULL PRIMARY KEY,
        setting_value TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function base64UrlEncode(string $bin): string {
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

function ensureVapidKeys(PDO $conn): array {
    ensureSystemSettingsTable($conn);
    $pub = getSystemSettingLocal($conn, 'vapid_public_key', '');
    $priv = getSystemSettingLocal($conn, 'vapid_private_key', '');
    $subj = getSystemSettingLocal($conn, 'vapid_subject', 'mailto:admin@example.com');
    if ($pub && $priv) {
        return ['public' => $pub, 'private' => $priv, 'subject' => $subj];
    }
    if (!function_exists('openssl_pkey_new')) {
        throw new Exception('OpenSSL is required for VAPID key generation');
    }
    // Generate EC P-256 keypair
    $res = openssl_pkey_new([
        'private_key_type' => OPENSSL_KEYTYPE_EC,
        'curve_name' => 'prime256v1',
    ]);
    if ($res === false) {
        throw new Exception('Failed to generate VAPID keypair');
    }
    $details = openssl_pkey_get_details($res);
    if (!$details || empty($details['ec'])) {
        throw new Exception('OpenSSL EC key details unavailable');
    }
    $x = $details['ec']['x'];
    $y = $details['ec']['y'];
    $d = $details['ec']['d'] ?? null;
    if (!$d) {
        openssl_pkey_export($res, $pem);
        $priv_b64u = base64UrlEncode($pem);
    } else {
        $priv_b64u = base64UrlEncode($d);
    }
    $uncompressed = "\x04" . $x . $y; // 65 bytes
    $pub_b64u = base64UrlEncode($uncompressed);

    // Store to system_settings
    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('vapid_public_key', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([$pub_b64u]);
    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('vapid_private_key', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([$priv_b64u]);
    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('vapid_subject', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([$subj]);

    return ['public' => $pub_b64u, 'private' => $priv_b64u, 'subject' => $subj];
}

function getVapidKeys(PDO $conn): array {
    ensureSystemSettingsTable($conn);
    $pub = getSystemSettingLocal($conn, 'vapid_public_key', '');
    $priv = getSystemSettingLocal($conn, 'vapid_private_key', '');
    $subj = getSystemSettingLocal($conn, 'vapid_subject', 'mailto:admin@example.com');
    if (!$pub || !$priv) {
        return ensureVapidKeys($conn);
    }
    return ['public' => $pub, 'private' => $priv, 'subject' => $subj];
}