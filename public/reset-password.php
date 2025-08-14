<?php
require_once '../config/config.php';
require_once '../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if ($password === '' || $confirm === '') {
        $error = 'Please fill all fields.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        try {
            $stmt = $conn->prepare('SELECT pr.user_id FROM password_resets pr WHERE pr.token = ? AND pr.expires_at > NOW()');
            $stmt->execute([$token]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) { throw new Exception('Invalid or expired token.'); }
            $userId = (int)$row['user_id'];
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $upd = $conn->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $upd->execute([$hash, $userId]);
            $del = $conn->prepare('DELETE FROM password_resets WHERE user_id = ?');
            $del->execute([$userId]);
            $success = 'Password has been reset. You can now log in.';
        } catch (Throwable $t) { $error = $t->getMessage(); }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo __('reset_password'); ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-6">
      <div class="card shadow-sm">
        <div class="card-header"><strong><?php echo __('reset_password'); ?></strong></div>
        <div class="card-body">
          <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
          <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
          <form method="POST">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <div class="mb-3">
              <label class="form-label"><?php echo __('new_password'); ?></label>
              <input type="password" class="form-control" name="password" required>
            </div>
            <div class="mb-3">
              <label class="form-label"><?php echo __('confirm_password'); ?></label>
              <input type="password" class="form-control" name="confirm_password" required>
            </div>
            <div class="d-flex justify-content-between">
              <a class="btn btn-secondary" href="<?php echo $base_url; ?>login.php"><?php echo __('back_to_login'); ?></a>
              <button type="submit" class="btn btn-primary"><?php echo __('reset_password'); ?></button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>