<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
requireAuth();
requireRole('super_admin');
require_once '../../../includes/header.php';

$db = new Database();
$conn = $db->getConnection();

// Ensure pages table exists
$conn->exec("CREATE TABLE IF NOT EXISTS pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    category VARCHAR(50) NULL,
    content MEDIUMTEXT NULL,
    show_in_footer TINYINT(1) DEFAULT 1,
    status VARCHAR(20) DEFAULT 'published',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM pages WHERE id = ?");
    $stmt->execute([$id]);
    echo "<script>location.href='index.php?msg=".urlencode('Page deleted')."';</script>";
    exit;
}

$msg = $_GET['msg'] ?? '';

// Load pages
$stmt = $conn->query("SELECT * FROM pages ORDER BY category, title");
$pages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="container-fluid">
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-file-alt"></i> <?php echo __('pages'); ?></h1>
    <a href="edit.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> <?php echo __('add_page'); ?></a>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

  <div class="card shadow">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-striped datatable" id="pagesTable">
          <thead><tr>
            <th><?php echo __('title'); ?></th>
            <th><?php echo __('slug'); ?></th>
            <th><?php echo __('category'); ?></th>
            <th><?php echo __('footer'); ?></th>
            <th><?php echo __('status'); ?></th>
            <th><?php echo __('updated'); ?></th>
            <th><?php echo __('actions'); ?></th>
          </tr></thead>
          <tbody>
          <?php foreach ($pages as $p): ?>
            <tr>
              <td class="searchable" data-title="<?php echo htmlspecialchars($p['title']); ?>"><?php echo htmlspecialchars($p['title']); ?></td>
              <td><?php echo htmlspecialchars($p['slug']); ?></td>
              <td><?php echo htmlspecialchars($p['category'] ?: ''); ?></td>
              <td><?php echo ((int)$p['show_in_footer'] ? '<span class="badge bg-success">'.__('yes').'</span>' : '<span class="badge bg-secondary">'.__('no').'</span>'); ?></td>
              <td><?php echo htmlspecialchars($p['status']); ?></td>
              <td><?php echo htmlspecialchars($p['updated_at']); ?></td>
              <td>
                <a class="btn btn-sm btn-outline-primary" href="/constract360/construction/public/pages/page.php?slug=<?php echo urlencode($p['slug']); ?>" target="_blank"><?php echo __('view'); ?></a>
                <a class="btn btn-sm btn-primary" href="edit.php?id=<?php echo (int)$p['id']; ?>"><?php echo __('edit'); ?></a>
                <a class="btn btn-sm btn-outline-danger" href="?delete=<?php echo (int)$p['id']; ?>" onclick="return confirm('<?php echo __('delete_this_page'); ?>');"><?php echo __('delete'); ?></a>
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