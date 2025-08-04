<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/header.php';

// Check if user is authenticated and has super admin role
requireAuth();
requireRole('super_admin');

$db = new Database();
$conn = $db->getConnection();

$error = '';
$success = '';

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_file'])) {
    $file = $_FILES['import_file'];
    $language_id = (int)($_POST['language_id'] ?? 0);
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'File upload failed.';
    } elseif ($file['type'] !== 'text/csv' && $file['type'] !== 'application/vnd.ms-excel') {
        $error = 'Please upload a CSV file.';
    } elseif ($language_id <= 0) {
        $error = 'Please select a language.';
    } else {
        try {
            $handle = fopen($file['tmp_name'], 'r');
            $imported = 0;
            $updated = 0;
            
            // Skip header row
            fgetcsv($handle);
            
            while (($data = fgetcsv($handle)) !== false) {
                if (count($data) >= 2) {
                    $key = trim($data[0]);
                    $value = trim($data[1]);
                    
                    if (!empty($key) && !empty($value)) {
                        // Check if translation exists
                        $stmt = $conn->prepare("SELECT id FROM language_translations WHERE language_id = ? AND translation_key = ?");
                        $stmt->execute([$language_id, $key]);
                        
                        if ($stmt->fetch()) {
                            // Update existing
                            $stmt = $conn->prepare("UPDATE language_translations SET translation_value = ? WHERE language_id = ? AND translation_key = ?");
                            $stmt->execute([$value, $language_id, $key]);
                            $updated++;
                        } else {
                            // Insert new
                            $stmt = $conn->prepare("INSERT INTO language_translations (language_id, translation_key, translation_value) VALUES (?, ?, ?)");
                            $stmt->execute([$language_id, $key, $value]);
                            $imported++;
                        }
                    }
                }
            }
            
            fclose($handle);
            $success = "Import completed: $imported new translations, $updated updated translations.";
            
        } catch (Exception $e) {
            $error = 'Error importing translations: ' . $e->getMessage();
        }
    }
}

// Get all languages
$stmt = $conn->prepare("SELECT * FROM languages ORDER BY language_name");
$stmt->execute();
$languages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-upload"></i> Import Translations
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
        <div class="col-md-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Import Translations</h6>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="language_id" class="form-label">Select Language *</label>
                            <select class="form-control" id="language_id" name="language_id" required>
                                <option value="">Choose a language...</option>
                                <?php foreach ($languages as $lang): ?>
                                    <option value="<?php echo $lang['id']; ?>">
                                        <?php echo htmlspecialchars($lang['language_name']); ?> (<?php echo htmlspecialchars($lang['language_name_native']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="import_file" class="form-label">CSV File *</label>
                            <input type="file" class="form-control" id="import_file" name="import_file" 
                                   accept=".csv" required>
                            <small class="form-text text-muted">
                                Upload a CSV file with columns: Translation Key, Translation Value
                            </small>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="overwrite" name="overwrite">
                                <label class="form-check-label" for="overwrite">
                                    Overwrite existing translations
                                </label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Import Translations
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Instructions</h6>
                </div>
                <div class="card-body">
                    <h6>CSV Format:</h6>
                    <p class="text-muted">
                        Your CSV file should have the following format:
                    </p>
                    <pre class="bg-light p-2 rounded">
translation_key,translation_value
dashboard,Dashboard
employees,Employees
settings,Settings
                    </pre>
                    
                    <h6 class="mt-3">Download Template:</h6>
                    <a href="export-template.php" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-download"></i> Download CSV Template
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>