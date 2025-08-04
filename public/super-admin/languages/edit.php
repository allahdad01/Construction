<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/header.php';

// Check if user is authenticated and has super admin role
requireAuth();
requireRole('super_admin');

$db = new Database();
$conn = $db->getConnection();

$language_id = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

if (!$language_id) {
    header('Location: /constract360/construction/public/super-admin/languages/');
    exit;
}

// Get language details
$stmt = $conn->prepare("SELECT * FROM languages WHERE id = ?");
$stmt->execute([$language_id]);
$language = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$language) {
    header('Location: /constract360/construction/public/super-admin/languages/');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $language_code = trim($_POST['language_code'] ?? '');
    $language_name = trim($_POST['language_name'] ?? '');
    $language_name_native = trim($_POST['language_name_native'] ?? '');
    $direction = $_POST['direction'] ?? 'ltr';
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Validation
    if (empty($language_code)) {
        $error = 'Language code is required.';
    } elseif (empty($language_name)) {
        $error = 'Language name is required.';
    } elseif (empty($language_name_native)) {
        $error = 'Native language name is required.';
    } else {
        try {
            // Check if language code is already used by another language
            $stmt = $conn->prepare("SELECT id FROM languages WHERE language_code = ? AND id != ?");
            $stmt->execute([$language_code, $language_id]);
            if ($stmt->fetch()) {
                $error = 'This language code is already in use by another language.';
            } else {
                // Update language
                $stmt = $conn->prepare("
                    UPDATE languages SET 
                        language_code = ?, 
                        language_name = ?, 
                        language_name_native = ?, 
                        direction = ?, 
                        is_active = ?
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $language_code, $language_name, $language_name_native,
                    $direction, $is_active, $language_id
                ]);

                $success = 'Language updated successfully!';
                
                // Refresh language data
                $stmt = $conn->prepare("SELECT * FROM languages WHERE id = ?");
                $stmt->execute([$language_id]);
                $language = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            $error = 'Error updating language: ' . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-edit"></i> Edit Language
        </h1>
        <div>
            <a href="/constract360/construction/public/super-admin/languages/" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Languages
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Edit Language Information</h6>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="language_code" class="form-label">Language Code *</label>
                            <input type="text" class="form-control" id="language_code" name="language_code" 
                                   value="<?php echo htmlspecialchars($language['language_code']); ?>" required>
                            <small class="form-text text-muted">e.g., en, es, fr, ar</small>
                        </div>

                        <div class="mb-3">
                            <label for="language_name" class="form-label">Language Name (English) *</label>
                            <input type="text" class="form-control" id="language_name" name="language_name" 
                                   value="<?php echo htmlspecialchars($language['language_name']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="language_name_native" class="form-label">Language Name (Native) *</label>
                            <input type="text" class="form-control" id="language_name_native" name="language_name_native" 
                                   value="<?php echo htmlspecialchars($language['language_name_native']); ?>" required>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="direction" class="form-label">Text Direction</label>
                            <select class="form-control" id="direction" name="direction">
                                <option value="ltr" <?php echo $language['direction'] === 'ltr' ? 'selected' : ''; ?>>Left to Right (LTR)</option>
                                <option value="rtl" <?php echo $language['direction'] === 'rtl' ? 'selected' : ''; ?>>Right to Left (RTL)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                       <?php echo $language['is_active'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_active">
                                    Language is active
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="alert alert-info">
                                <strong>Language ID:</strong> <?php echo $language['id']; ?><br>
                                <strong>Created:</strong> <?php echo formatDate($language['created_at']); ?><br>
                                <strong>Updated:</strong> <?php echo formatDate($language['updated_at']); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Language
                        </button>
                        <a href="/constract360/construction/public/super-admin/languages/" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Auto-hide alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        alert.style.display = 'none';
    });
}, 5000);
</script>

<?php require_once '../../../includes/footer.php'; ?>