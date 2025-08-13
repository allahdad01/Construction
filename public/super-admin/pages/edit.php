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
    <h1 class="h3 mb-0 text-gray-800"><?php echo $id ? __('edit_page') : __('add_page'); ?></h1>
    <a href="index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> <?php echo __('back'); ?></a>
  </div>

  <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <div class="card shadow">
    <div class="card-body">
      <form method="POST">
        <div class="row">
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label"><?php echo __('title'); ?> *</label>
              <input type="text" class="form-control" name="title" required value="<?php echo htmlspecialchars($page['title']); ?>">
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label"><?php echo __('slug'); ?> *</label>
              <input type="text" class="form-control" name="slug" required value="<?php echo htmlspecialchars($page['slug']); ?>">
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-4">
            <div class="mb-3">
              <label class="form-label"><?php echo __('category'); ?></label>
              <select class="form-control" name="category">
                <option value="legal" <?php echo $page['category']==='legal'?'selected':''; ?>><?php echo __('legal'); ?></option>
                <option value="company" <?php echo $page['category']==='company'?'selected':''; ?>><?php echo __('company'); ?></option>
                <option value="help" <?php echo $page['category']==='help'?'selected':''; ?>><?php echo __('help'); ?></option>
              </select>
            </div>
          </div>
          <div class="col-md-4">
            <div class="mb-3">
              <label class="form-label"><?php echo __('status'); ?></label>
              <select class="form-control" name="status">
                <option value="published" <?php echo $page['status']==='published'?'selected':''; ?>><?php echo __('published'); ?></option>
                <option value="draft" <?php echo $page['status']==='draft'?'selected':''; ?>><?php echo __('draft'); ?></option>
              </select>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-check mt-4">
              <input class="form-check-input" type="checkbox" name="show_in_footer" id="show_in_footer" <?php echo ((int)$page['show_in_footer'] ? 'checked' : ''); ?>>
              <label class="form-check-label" for="show_in_footer"><?php echo __('show_in_footer'); ?></label>
            </div>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label"><?php echo __('content'); ?></label>
          <textarea class="form-control" name="content" rows="12"><?php echo htmlspecialchars($page['content']); ?></textarea>
        </div>

        <div class="text-end">
          <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> <?php echo __('save'); ?></button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require_once '../../../includes/footer.php'; ?>