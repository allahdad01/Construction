<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
requireAuth();
require_once '../../includes/header.php';

$db = new Database();
$conn = $db->getConnection();

$lang = $_GET['lang'] ?? '';
$task = $_GET['task'] ?? '';

// Languages available in tutorials
$langs = $conn->query("SELECT DISTINCT language_code FROM tutorials WHERE status='published' ORDER BY language_code")->fetchAll(PDO::FETCH_COLUMN);

$tutorials = [];
$tasks = [];
if ($lang) {
    $stmt = $conn->prepare("SELECT DISTINCT task_name FROM tutorials WHERE status='published' AND language_code=? ORDER BY task_name");
    $stmt->execute([$lang]);
    $tasks = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if ($task) {
        $q = $conn->prepare("SELECT * FROM tutorials WHERE status='published' AND language_code=? AND task_name=? ORDER BY title");
        $q->execute([$lang, $task]);
        $tutorials = $q->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<div class="container-fluid">
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-chalkboard-teacher"></i> <?php echo __('tutorials'); ?></h1>
  </div>

  <div class="card shadow mb-3"><div class="card-body">
    <form class="row g-3">
      <div class="col-md-4">
        <label class="form-label"><?php echo __('language'); ?></label>
        <select class="form-control" name="lang" onchange="this.form.submit()">
          <option value=""><?php echo __('select_language'); ?></option>
          <?php foreach ($langs as $lc): ?>
            <option value="<?php echo htmlspecialchars($lc); ?>" <?php echo $lang===$lc?'selected':''; ?>><?php echo htmlspecialchars(getLanguageName($lc)); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label"><?php echo __('task'); ?></label>
        <select class="form-control" name="task" <?php echo $lang?'':'disabled'; ?> onchange="this.form.submit()">
          <option value=""><?php echo __('select_task'); ?></option>
          <?php foreach ($tasks as $tn): ?>
            <option value="<?php echo htmlspecialchars($tn); ?>" <?php echo $task===$tn?'selected':''; ?>><?php echo htmlspecialchars($tn); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4 d-flex align-items-end">
        <a class="btn btn-secondary" href="index.php"><?php echo __('clear'); ?></a>
      </div>
    </form>
  </div></div>

  <?php if ($lang && $task): ?>
    <div class="row">
      <?php foreach ($tutorials as $t): ?>
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm mb-4 h-100">
            <div class="card-body">
              <h5 class="card-title mb-2"><?php echo htmlspecialchars($t['title']); ?></h5>
              <div class="mb-2"><span class="badge bg-info"><?php echo htmlspecialchars(strtoupper($t['type'])); ?></span></div>
              <?php if ($t['type']==='video'): ?>
                <?php if (!empty($t['embed_url'])): ?>
                  <div class="ratio ratio-16x9 mb-2"><iframe src="<?php echo htmlspecialchars($t['embed_url']); ?>" allowfullscreen></iframe></div>
                <?php elseif (!empty($t['file_path'])): ?>
                  <video class="w-100 mb-2" controls src="/constract360/construction/<?php echo htmlspecialchars($t['file_path']); ?>"></video>
                <?php endif; ?>
              <?php elseif ($t['type']==='pdf'): ?>
                <?php if (!empty($t['file_path'])): ?>
                  <a class="btn btn-outline-primary btn-sm" target="_blank" href="/constract360/construction/<?php echo htmlspecialchars($t['file_path']); ?>"><i class="fas fa-file-pdf me-1"></i> <?php echo __('open_pdf'); ?></a>
                <?php endif; ?>
              <?php elseif ($t['type']==='text'): ?>
                <div class="border rounded p-2" style="max-height:180px; overflow:auto; white-space:pre-wrap"><?php echo nl2br(htmlspecialchars($t['content_text'])); ?></div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php elseif ($lang): ?>
    <div class="alert alert-info"><?php echo __('select_task_to_view_tutorials'); ?></div>
  <?php else: ?>
    <div class="alert alert-secondary"><?php echo __('select_language_to_begin'); ?></div>
  <?php endif; ?>
</div>
<?php require_once '../../includes/footer.php'; ?>