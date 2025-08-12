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
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-file-alt"></i> Pages</h1>
    <a href="edit.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add Page</a>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

  <div class="card shadow">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-striped datatable" id="pagesTable">
          <thead><tr>
            <th>Title</th>
            <th>Slug</th>
            <th>Category</th>
            <th>Footer</th>
            <th>Status</th>
            <th>Updated</th>
            <th>Actions</th>
          </tr></thead>
          <tbody>
          <?php foreach ($pages as $p): ?>
            <tr>
              <td class="searchable" data-title="<?php echo htmlspecialchars($p['title']); ?>"><?php echo htmlspecialchars($p['title']); ?></td>
              <td><?php echo htmlspecialchars($p['slug']); ?></td>
              <td><?php echo htmlspecialchars($p['category'] ?: ''); ?></td>
              <td><?php echo ((int)$p['show_in_footer'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>'); ?></td>
              <td><?php echo htmlspecialchars($p['status']); ?></td>
              <td><?php echo htmlspecialchars($p['updated_at']); ?></td>
              <td>
                <a class="btn btn-sm btn-outline-primary" href="/constract360/construction/public/pages/page.php?slug=<?php echo urlencode($p['slug']); ?>" target="_blank">View</a>
                <a class="btn btn-sm btn-primary" href="edit.php?id=<?php echo (int)$p['id']; ?>">Edit</a>
                <a class="btn btn-sm btn-outline-danger" href="?delete=<?php echo (int)$p['id']; ?>" onclick="return confirm('Delete this page?');">Delete</a>
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