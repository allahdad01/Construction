<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/header.php';

// Check if user is authenticated and has super admin role
requireAuth();
requireRole('super_admin');

$db = new Database();
$conn = $db->getConnection();

$language_id = (int)($_GET['language_id'] ?? 0);
$error = '';
$success = '';

// Handle form submission for adding/updating translations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $translation_key = trim($_POST['translation_key'] ?? '');
    $translation_value = trim($_POST['translation_value'] ?? '');
    $target_language_id = (int)($_POST['language_id'] ?? $language_id);

    if (empty($translation_key) || empty($translation_value)) {
        $error = 'Translation key and value are required.';
    } else {
        try {
            // Check if translation already exists
            $stmt = $conn->prepare("SELECT id FROM language_translations WHERE language_id = ? AND translation_key = ?");
            $stmt->execute([$target_language_id, $translation_key]);
            
            if ($stmt->fetch()) {
                // Update existing translation
                $stmt = $conn->prepare("UPDATE language_translations SET translation_value = ? WHERE language_id = ? AND translation_key = ?");
                $stmt->execute([$translation_value, $target_language_id, $translation_key]);
                $success = 'Translation updated successfully!';
            } else {
                // Add new translation
                $stmt = $conn->prepare("INSERT INTO language_translations (language_id, translation_key, translation_value) VALUES (?, ?, ?)");
                $stmt->execute([$target_language_id, $translation_key, $translation_value]);
                $success = 'Translation added successfully!';
            }
        } catch (Exception $e) {
            $error = 'Error saving translation: ' . $e->getMessage();
        }
    }
}

// Get all languages
$stmt = $conn->prepare("SELECT * FROM languages ORDER BY language_name");
$stmt->execute();
$languages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get translations for selected language
$translations = [];
if ($language_id) {
    $stmt = $conn->prepare("SELECT * FROM language_translations WHERE language_id = ? ORDER BY translation_key");
    $stmt->execute([$language_id]);
    $translations = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get selected language details
$selected_language = null;
if ($language_id) {
    $stmt = $conn->prepare("SELECT * FROM languages WHERE id = ?");
    $stmt->execute([$language_id]);
    $selected_language = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-language"></i> Manage Translations
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

    <div class="row">
        <!-- Language Selection -->
        <div class="col-md-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Select Language</h6>
                </div>
                <div class="card-body">
                    <form method="GET">
                        <div class="mb-3">
                            <label for="language_id" class="form-label">Choose Language</label>
                            <select class="form-control" id="language_id" name="language_id" onchange="this.form.submit()">
                                <option value="">Select a language...</option>
                                <?php foreach ($languages as $lang): ?>
                                    <option value="<?php echo $lang['id']; ?>" <?php echo $language_id == $lang['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($lang['language_name']); ?> (<?php echo htmlspecialchars($lang['language_name_native']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>

                    <?php if ($selected_language): ?>
                        <div class="alert alert-info">
                            <strong>Language:</strong> <?php echo htmlspecialchars($selected_language['language_name']); ?><br>
                            <strong>Native Name:</strong> <?php echo htmlspecialchars($selected_language['language_name_native']); ?><br>
                            <strong>Direction:</strong> <?php echo strtoupper($selected_language['direction']); ?><br>
                            <strong>Status:</strong> 
                            <span class="badge <?php echo $selected_language['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                <?php echo $selected_language['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Add Translation Form -->
            <?php if ($language_id): ?>
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Add/Edit Translation</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="language_id" value="<?php echo $language_id; ?>">
                            
                            <div class="mb-3">
                                <label for="translation_key" class="form-label">Translation Key *</label>
                                <input type="text" class="form-control" id="translation_key" name="translation_key" 
                                       placeholder="e.g., dashboard, employees, settings" required>
                                <small class="form-text text-muted">Use lowercase with underscores</small>
                            </div>

                            <div class="mb-3">
                                <label for="translation_value" class="form-label">Translation Value *</label>
                                <textarea class="form-control" id="translation_value" name="translation_value" 
                                          rows="3" placeholder="Enter the translation..." required></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-save"></i> Save Translation
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Translations List -->
        <div class="col-md-8">
            <?php if ($language_id): ?>
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">
                            Translations for <?php echo htmlspecialchars($selected_language['language_name']); ?>
                        </h6>
                        <span class="badge bg-primary"><?php echo count($translations); ?> translations</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($translations)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-language fa-3x text-gray-300 mb-3"></i>
                                <p class="text-gray-500">No translations found for this language.</p>
                                <p class="text-muted">Add your first translation using the form on the left.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Translation Key</th>
                                            <th>Translation Value</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($translations as $translation): ?>
                                            <tr>
                                                <td>
                                                    <code><?php echo htmlspecialchars($translation['translation_key']); ?></code>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($translation['translation_value']); ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-warning" 
                                                                onclick="editTranslation('<?php echo htmlspecialchars($translation['translation_key']); ?>', '<?php echo htmlspecialchars($translation['translation_value']); ?>')">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <a href="delete-translation.php?id=<?php echo $translation['id']; ?>&language_id=<?php echo $language_id; ?>" 
                                                           class="btn btn-sm btn-danger"
                                                           onclick="return confirm('Are you sure you want to delete this translation?')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="card shadow mb-4">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-language fa-4x text-gray-300 mb-3"></i>
                        <h5 class="text-gray-600">Select a Language</h5>
                        <p class="text-muted">Choose a language from the dropdown to manage its translations.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function editTranslation(key, value) {
    document.getElementById('translation_key').value = key;
    document.getElementById('translation_value').value = value;
    document.getElementById('translation_key').focus();
}

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        alert.style.display = 'none';
    });
}, 5000);
</script>

<?php require_once '../../../includes/footer.php'; ?>