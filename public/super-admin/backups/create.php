<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
requireAuth();
requireRole('super_admin');

$backupDirRel = 'public/backups/';
$backupDirAbs = __DIR__ . '/../../../' . $backupDirRel;
if (!is_dir($backupDirAbs)) { @mkdir($backupDirAbs, 0755, true); }

$db = new Database();
$conn = $db->getConnection();

$filename = 'backup_' . date('Ymd_His') . '.sql';
$abs = $backupDirAbs . $filename;

try {
    // Try mysqldump if available
    $dsn = $db->getDSN(); // May not exist; fallback to env constants
} catch (Throwable $t) {}

$host = DB_HOST ?? 'localhost';
$name = DB_NAME ?? '';
$user = DB_USER ?? '';
$pass = DB_PASS ?? '';

$dumpOk = false;
$mysqldump = trim(shell_exec('which mysqldump 2>/dev/null'));
if ($mysqldump && is_executable($mysqldump)) {
    $cmd = sprintf('%s --no-tablespaces -h%s -u%s -p%s %s > %s', escapeshellcmd($mysqldump), escapeshellarg($host), escapeshellarg($user), escapeshellarg($pass), escapeshellarg($name), escapeshellarg($abs));
    $ret = null; system($cmd, $ret);
    $dumpOk = ($ret === 0) && file_exists($abs) && filesize($abs) > 0;
}

if (!$dumpOk) {
    // Fallback: simple PDO-based dump
    $fh = fopen($abs, 'w');
    fwrite($fh, "-- Simple SQL dump\nSET NAMES utf8mb4;\n\n");
    $tables = $conn->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $create = $conn->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_ASSOC);
        fwrite($fh, "DROP TABLE IF EXISTS `{$table}`;\n" . $create['Create Table'] . ";\n\n");
        $rows = $conn->query('SELECT * FROM `'.$table.'`', PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $cols = array_map(fn($k)=>'`'.str_replace('`','``',$k).'`', array_keys($row));
            $vals = array_map(function($v){
                if ($v === null) return 'NULL';
                return "'".str_replace(["\\","'"],["\\\\","\\'"], (string)$v)."'";
            }, array_values($row));
            fwrite($fh, sprintf("INSERT INTO `%s` (%s) VALUES (%s);\n", $table, implode(',', $cols), implode(',', $vals)));
        }
        fwrite($fh, "\n");
    }
    fclose($fh);
}

header('Location: index.php?msg=' . urlencode('Backup created: ' . $filename));
exit;