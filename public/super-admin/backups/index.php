<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
requireAuth();
requireRole('super_admin');
require_once '../../../includes/header.php';

$backupDirRel = 'public/backups/';
$backupDirAbs = __DIR__ . '/../../../' . $backupDirRel;
if (!is_dir($backupDirAbs)) { @mkdir($backupDirAbs, 0755, true); }

$msg = $_GET['msg'] ?? '';

// List .sql files
$files = [];
foreach (glob($backupDirAbs . '*.sql') as $path) {
    $files[] = [
        'name' => basename($path),
        'size' => filesize($path),
        'mtime' => filemtime($path),
    ];
}
usort($files, function($a,$b){ return $b['mtime'] <=> $a['mtime']; });
?>
<div class="container-fluid">
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-database"></i> Database Backups</h1>
    <a href="create.php" class="btn btn-primary btn-sm" onclick="return confirm('Create a new database backup now?');"><i class="fas fa-plus"></i> Create Backup</a>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

  <div class="card shadow">
    <div class="card-body">
      <?php if (empty($files)): ?>
        <div class="text-muted">No backups found.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-striped">
            <thead>
              <tr><th>Filename</th><th>Size</th><th>Created</th><th>Actions</th></tr>
            </thead>
            <tbody>
              <?php foreach ($files as $f): ?>
                <tr>
                  <td class="searchable" data-name="<?php echo htmlspecialchars($f['name']); ?>"><?php echo htmlspecialchars($f['name']); ?></td>
                  <td><?php echo number_format($f['size'] / 1024, 2); ?> KB</td>
                  <td><?php echo date('Y-m-d H:i:s', $f['mtime']); ?></td>
                  <td>
                    <a class="btn btn-sm btn-outline-primary" href="download.php?file=<?php echo urlencode($f['name']); ?>"><i class="fas fa-download"></i> Download</a>
                    <a class="btn btn-sm btn-outline-danger" href="delete.php?file=<?php echo urlencode($f['name']); ?>" onclick="return confirm('Delete this backup?');"><i class="fas fa-trash"></i> Delete</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require_once '../../../includes/footer.php'; ?>