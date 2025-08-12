<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
requireAuth();
requireRole('super_admin');
require_once '../../../includes/header.php';

$db = new Database();
$conn = $db->getConnection();

$id = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

// Load existing page
$page = ['title'=>'','slug'=>'','category'=>'legal','content'=>'','show_in_footer'=>1,'status'=>'published'];
if ($id) {
    $stmt = $conn->prepare('SELECT * FROM pages WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) { $page = $row; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $title = trim($_POST['title'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $category = trim($_POST['category'] ?? 'legal');
        $content = $_POST['content'] ?? '';
        $show_in_footer = isset($_POST['show_in_footer']) ? 1 : 0;
        $status = $_POST['status'] ?? 'published';
        if ($title === '' || $slug === '') { throw new Exception('Title and Slug are required.'); }

        if ($id) {
            $stmt = $conn->prepare('UPDATE pages SET title=?, slug=?, category=?, content=?, show_in_footer=?, status=? WHERE id=?');
            $stmt->execute([$title,$slug,$category,$content,$show_in_footer,$status,$id]);
        } else {
            $stmt = $conn->prepare('INSERT INTO pages (title, slug, category, content, show_in_footer, status) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$title,$slug,$category,$content,$show_in_footer,$status]);
            $id = (int)$conn->lastInsertId();
        }
        echo "<script>location.href='index.php?msg=".urlencode('Page saved')."';</script>";
        exit;
    } catch (Throwable $t) { $error = $t->getMessage(); }
}
?>
<div class="container-fluid">
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><?php echo $id ? 'Edit Page' : 'Add Page'; ?></h1>
    <a href="index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
  </div>

  <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <div class="card shadow">
    <div class="card-body">
      <form method="POST">
        <div class="row">
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label">Title *</label>
              <input type="text" class="form-control" name="title" required value="<?php echo htmlspecialchars($page['title']); ?>">
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label">Slug *</label>
              <input type="text" class="form-control" name="slug" required value="<?php echo htmlspecialchars($page['slug']); ?>">
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-4">
            <div class="mb-3">
              <label class="form-label">Category</label>
              <select class="form-control" name="category">
                <option value="legal" <?php echo $page['category']==='legal'?'selected':''; ?>>Legal</option>
                <option value="company" <?php echo $page['category']==='company'?'selected':''; ?>>Company</option>
                <option value="help" <?php echo $page['category']==='help'?'selected':''; ?>>Help</option>
              </select>
            </div>
          </div>
          <div class="col-md-4">
            <div class="mb-3">
              <label class="form-label">Status</label>
              <select class="form-control" name="status">
                <option value="published" <?php echo $page['status']==='published'?'selected':''; ?>>Published</option>
                <option value="draft" <?php echo $page['status']==='draft'?'selected':''; ?>>Draft</option>
              </select>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-check mt-4">
              <input class="form-check-input" type="checkbox" name="show_in_footer" id="show_in_footer" <?php echo ((int)$page['show_in_footer'] ? 'checked' : ''); ?>>
              <label class="form-check-label" for="show_in_footer">Show in Footer</label>
            </div>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">Content</label>
          <textarea class="form-control" name="content" rows="12"><?php echo htmlspecialchars($page['content']); ?></textarea>
        </div>

        <div class="text-end">
          <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require_once '../../../includes/footer.php'; ?>