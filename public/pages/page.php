<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') { http_response_code(404); echo 'Page not found'; exit; }

$stmt = $conn->prepare("SELECT title, content, status FROM pages WHERE slug = ? AND status = 'published'");
$stmt->execute([$slug]);
$page = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$page) { http_response_code(404); echo 'Page not found'; exit; }

function getSystemSettingLocal($conn, $key, $default = '') {
    $s = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $s->execute([$key]);
    $r = $s->fetch(PDO::FETCH_ASSOC); return $r ? $r['setting_value'] : $default;
}
$platform_name = getSystemSettingLocal($conn, 'platform_name', 'Construction SaaS Platform');
$primary_color = getSystemSettingLocal($conn, 'primary_color', '#243447');
$accent_color = getSystemSettingLocal($conn, 'accent_color', '#F17300');
$favicon = getSystemSettingLocal($conn, 'platform_favicon', '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($page['title']); ?> - <?php echo htmlspecialchars($platform_name); ?></title>
  <?php if ($favicon): ?><link rel="icon" href="<?php echo $base_url; ?><?php echo htmlspecialchars($favicon); ?>"><?php endif; ?>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .topbar{background: linear-gradient(135deg, <?php echo $primary_color; ?>, <?php echo $accent_color; ?>); color:#fff}
  </style>
</head>
<body>
  <div class="topbar py-3 mb-4">
    <div class="container d-flex justify-content-between align-items-center">
      <strong><?php echo htmlspecialchars($platform_name); ?></strong>
      <a class="btn btn-light btn-sm" href="<?php echo $base_url; ?>"><?php echo __('home'); ?></a>
    </div>
  </div>
  <div class="container">
    <h1 class="mb-3"><?php echo htmlspecialchars($page['title']); ?></h1>
    <div class="card shadow-sm mb-5"><div class="card-body">
      <?php echo $page['content']; ?>
    </div></div>
  </div>
</body>
</html>