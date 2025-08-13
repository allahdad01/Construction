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

// Load available languages
$langs = $conn->query("SELECT language_code, language_name_native FROM languages WHERE is_active=1 ORDER BY language_name_native")->fetchAll(PDO::FETCH_ASSOC);

$tutorial = ['language_code'=>'en','task_name'=>'general','title'=>'','type'=>'video','file_path'=>'','embed_url'=>'','content_text'=>'','status'=>'published'];
if ($id) {
    $stmt = $conn->prepare('SELECT * FROM tutorials WHERE id=?');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) { $tutorial = $row; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $tutorial['language_code'] = $_POST['language_code'] ?? 'en';
        $tutorial['task_name'] = trim($_POST['task_name'] ?? 'general');
        $tutorial['title'] = trim($_POST['title'] ?? '');
        $tutorial['type'] = $_POST['type'] ?? 'video';
        $tutorial['embed_url'] = trim($_POST['embed_url'] ?? '');
        $tutorial['content_text'] = $_POST['content_text'] ?? '';
        $tutorial['status'] = $_POST['status'] ?? 'published';
        if ($tutorial['title'] === '' || $tutorial['task_name'] === '') { throw new Exception('Title and Task are required.'); }

        // Handle file upload (for pdf/video file)
        $upload_path = '';
        if (!empty($_FILES['file_upload']['name'])) {
            $allowed = ['application/pdf','video/mp4','video/webm','video/ogg'];
            if (!in_array($_FILES['file_upload']['type'], $allowed)) { throw new Exception('Invalid file type.'); }
            $destDir = __DIR__ . '/../../../public/uploads/tutorials/';
            if (!is_dir($destDir)) { mkdir($destDir, 0755, true); }
            $ext = pathinfo($_FILES['file_upload']['name'], PATHINFO_EXTENSION);
            $fname = 'tutorial_' . time() . '_' . uniqid() . '.' . $ext;
            if (!move_uploaded_file($_FILES['file_upload']['tmp_name'], $destDir . $fname)) {
                throw new Exception('Failed to upload file.');
            }
            $upload_path = 'public/uploads/tutorials/' . $fname;
            $tutorial['file_path'] = $upload_path;
        }

        if ($id) {
            $stmt = $conn->prepare('UPDATE tutorials SET language_code=?, task_name=?, title=?, type=?, file_path=COALESCE(?, file_path), embed_url=?, content_text=?, status=? WHERE id=?');
            $stmt->execute([$tutorial['language_code'],$tutorial['task_name'],$tutorial['title'],$tutorial['type'],($upload_path?:null),$tutorial['embed_url'],$tutorial['content_text'],$tutorial['status'],$id]);
        } else {
            $stmt = $conn->prepare('INSERT INTO tutorials (language_code, task_name, title, type, file_path, embed_url, content_text, status) VALUES (?,?,?,?,?,?,?,?)');
            $stmt->execute([$tutorial['language_code'],$tutorial['task_name'],$tutorial['title'],$tutorial['type'],($tutorial['file_path']??null),$tutorial['embed_url'],$tutorial['content_text'],$tutorial['status']]);
        }
        echo "<script>location.href='index.php?msg=".urlencode('Tutorial saved')."';</script>"; exit;
    } catch (Throwable $t) { $error = $t->getMessage(); }
}
?>
<div class="container-fluid">
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><?php echo $id ? 'Edit Tutorial' : 'Add Tutorial'; ?></h1>
    <a href="index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
  </div>
  <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <div class="card shadow">
    <div class="card-body">
      <form method="POST" enctype="multipart/form-data">
        <div class="row">
          <div class="col-md-4">
            <label class="form-label">Language</label>
            <select class="form-control" name="language_code">
              <?php foreach ($langs as $lg): ?>
                <option value="<?php echo htmlspecialchars($lg['language_code']); ?>" <?php echo $tutorial['language_code']===$lg['language_code']?'selected':''; ?><?php echo htmlspecialchars($lg['language_name_native']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Task *</label>
            <input class="form-control" name="task_name" required value="<?php echo htmlspecialchars($tutorial['task_name']); ?>" placeholder="e.g., salary-payments, contracts, reports">
          </div>
          <div class="col-md-4">
            <label class="form-label">Status</label>
            <select class="form-control" name="status">
              <option value="published" <?php echo $tutorial['status']==='published'?'selected':''; ?>>Published</option>
              <option value="draft" <?php echo $tutorial['status']==='draft'?'selected':''; ?>>Draft</option>
            </select>
          </div>
        </div>

        <div class="row mt-3">
          <div class="col-md-6">
            <label class="form-label">Title *</label>
            <input class="form-control" name="title" required value="<?php echo htmlspecialchars($tutorial['title']); ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Type</label>
            <select class="form-control" name="type" id="type">
              <option value="video" <?php echo $tutorial['type']==='video'?'selected':''; ?>>Video (embed or upload)</option>
              <option value="pdf" <?php echo $tutorial['type']==='pdf'?'selected':''; ?>>PDF</option>
              <option value="text" <?php echo $tutorial['type']==='text'?'selected':''; ?>>Text</option>
            </select>
          </div>
        </div>

        <div class="row mt-3">
          <div class="col-md-6">
            <label class="form-label">Embed URL (YouTube/Vimeo)</label>
            <input class="form-control" name="embed_url" placeholder="https://..." value="<?php echo htmlspecialchars($tutorial['embed_url']); ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Upload File (MP4 or PDF)</label>
            <input type="file" class="form-control" name="file_upload" accept=".pdf,video/mp4,video/webm,video/ogg">
            <?php if (!empty($tutorial['file_path'])): ?>
              <small class="text-muted">Current: <a href="/constract360/construction/<?php echo htmlspecialchars($tutorial['file_path']); ?>" target="_blank">Open</a></small>
            <?php endif; ?>
          </div>
        </div>

        <div class="mt-3">
          <label class="form-label">Text Content</label>
          <textarea class="form-control" name="content_text" rows="10" placeholder="Markdown or plain text accepted."><?php echo htmlspecialchars($tutorial['content_text']); ?></textarea>
        </div>

        <div class="text-end mt-3">
          <button class="btn btn-primary" type="submit"><i class="fas fa-save me-1"></i> Save Tutorial</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require_once '../../../includes/footer.php'; ?>