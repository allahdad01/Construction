<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
requireAuth();
requireRole('super_admin');
require_once '../../../includes/header.php';

$db = new Database();
$conn = $db->getConnection();

// Ensure tutorials table exists
$conn->exec("CREATE TABLE IF NOT EXISTS tutorials (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_id INT NULL,
  language_code VARCHAR(10) NOT NULL,
  task_name VARCHAR(120) NOT NULL,
  title VARCHAR(255) NOT NULL,
  type VARCHAR(20) NOT NULL,
  file_path VARCHAR(500) NULL,
  embed_url VARCHAR(500) NULL,
  content_text MEDIUMTEXT NULL,
  status VARCHAR(20) DEFAULT 'published',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_lang_task (language_code, task_name),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare('SELECT file_path FROM tutorials WHERE id=?');
    $stmt->execute([$id]);
    $fp = $stmt->fetchColumn();
    $del = $conn->prepare('DELETE FROM tutorials WHERE id=?');
    $del->execute([$id]);
    if ($fp) {
        $abs = __DIR__ . '/../../../' . $fp;
        if (file_exists($abs) && is_file($abs)) { @unlink($abs); }
    }
    echo "<script>location.href='index.php?msg=".urlencode('Tutorial deleted')."';</script>"; exit;
}

$msg = $_GET['msg'] ?? '';

// Load tutorials
$stmt = $conn->query("SELECT t.*, (SELECT language_name_native FROM languages WHERE language_code=t.language_code LIMIT 1) lang_name FROM tutorials t ORDER BY t.language_code, t.task_name, t.title");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="container-fluid">
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-chalkboard-teacher"></i> <?php echo __('tutorials'); ?></h1>
    <a href="add.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> <?php echo __('add_tutorial'); ?></a>
  </div>
  <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

  <div class="card shadow">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-striped datatable">
          <thead>
            <tr>
              <th><?php echo __('language'); ?></th>
              <th><?php echo __('task'); ?></th>
              <th><?php echo __('title'); ?></th>
              <th><?php echo __('type'); ?></th>
              <th><?php echo __('status'); ?></th>
              <th><?php echo __('updated'); ?></th>
              <th><?php echo __('actions'); ?></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?php echo htmlspecialchars($r['lang_name'] ?: $r['language_code']); ?></td>
              <td><?php echo htmlspecialchars($r['task_name']); ?></td>
              <td><?php echo htmlspecialchars($r['title']); ?></td>
              <td><?php echo htmlspecialchars($r['type']); ?></td>
              <td><?php echo htmlspecialchars($r['status']); ?></td>
              <td><?php echo htmlspecialchars($r['updated_at']); ?></td>
              <td>
                <a href="edit.php?id=<?php echo (int)$r['id']; ?>" class="btn btn-sm btn-primary"><?php echo __('edit'); ?></a>
                <a href="delete.php?id=<?php echo (int)$r['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('<?php echo __('delete_this_tutorial'); ?>');"><?php echo __('delete'); ?></a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require_once '../../../includes/footer.php'; ?>