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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $language_code = trim($_POST['language_code'] ?? '');
    $language_name = trim($_POST['language_name'] ?? '');
    $language_name_native = trim($_POST['language_name_native'] ?? '');
    $direction = $_POST['direction'] ?? 'ltr';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validation
    if (empty($language_code) || strlen($language_code) < 2 || strlen($language_code) > 5) {
        $error = 'Language code must be between 2 and 5 characters.';
    } elseif (empty($language_name)) {
        $error = 'Language name is required.';
    } elseif (empty($language_name_native)) {
        $error = 'Native language name is required.';
    } else {
        // Check if language code already exists
        $stmt = $conn->prepare("SELECT id FROM languages WHERE language_code = ?");
        $stmt->execute([$language_code]);
        if ($stmt->fetch()) {
            $error = 'Language code already exists.';
        } else {
            try {
                $stmt = $conn->prepare("
                    INSERT INTO languages (language_code, language_name, language_name_native, direction, is_active) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                if ($stmt->execute([$language_code, $language_name, $language_name_native, $direction, $is_active])) {
                    $language_id = $conn->lastInsertId();
                    $success = 'Language added successfully! Language ID: ' . $language_id;
                    
                    // Clear form data
                    $_POST = [];
                } else {
                    $error = 'Failed to add language.';
                }
            } catch (Exception $e) {
                $error = 'Error adding language: ' . $e->getMessage();
            }
        }
    }
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?php echo __('add_new_language'); ?></h1>
        <a href="index.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> <?php echo __('back_to_languages'); ?>
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('language_information'); ?></h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="language_code" class="form-label">
                                        <i class="fas fa-code"></i> <?php echo __('language_code'); ?> *
                                    </label>
                                    <input type="text" class="form-control" id="language_code" name="language_code" 
                                           value="<?php echo htmlspecialchars($_POST['language_code'] ?? ''); ?>" 
                                           maxlength="5" required>
                                    <small class="text-muted"><?php echo __('iso_language_code'); ?> (e.g., en, da, ps, fr, es)</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="language_name" class="form-label">
                                        <i class="fas fa-language"></i> <?php echo __('language_name'); ?> (English) *
                                    </label>
                                    <input type="text" class="form-control" id="language_name" name="language_name" 
                                           value="<?php echo htmlspecialchars($_POST['language_name'] ?? ''); ?>" required>
                                    <small class="text-muted"><?php echo __('language_name_in_english'); ?></small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="language_name_native" class="form-label">
                                        <i class="fas fa-font"></i> <?php echo __('native_language_name'); ?> *
                                    </label>
                                    <input type="text" class="form-control" id="language_name_native" name="language_name_native" 
                                           value="<?php echo htmlspecialchars($_POST['language_name_native'] ?? ''); ?>" required>
                                    <small class="text-muted"><?php echo __('language_name_in_its_native_script'); ?></small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="direction" class="form-label">
                                        <i class="fas fa-text-width"></i> <?php echo __('text_direction'); ?> *
                                    </label>
                                    <select class="form-control" id="direction" name="direction" required>
                                        <option value="ltr" <?php echo ($_POST['direction'] ?? 'ltr') === 'ltr' ? 'selected' : ''; ?>>Left to Right (LTR)</option>
                                        <option value="rtl" <?php echo ($_POST['direction'] ?? '') === 'rtl' ? 'selected' : ''; ?>>Right to Left (RTL)</option>
                                    </select>
                                    <small class="text-muted"><?php echo __('text_direction_for_this_language'); ?></small>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" 
                                       <?php echo isset($_POST['is_active']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_active">
                                    <i class="fas fa-check-circle"></i> <?php echo __('active_language'); ?>
                                </label>
                                <small class="text-muted d-block"><?php echo __('active_languages_are_available_for_companies_to_select'); ?></small>
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="index.php" class="btn btn-secondary me-md-2">
                                <i class="fas fa-times"></i> <?php echo __('cancel'); ?>
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> <?php echo __('add_language'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('information'); ?></h6>
                </div>
                <div class="card-body">
                    <h6><?php echo __('language_code_guidelines'); ?></h6>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-check text-success"></i> Use ISO 639-1 codes (2 letters)</li>
                        <li><i class="fas fa-check text-success"></i> Examples: en, fr, es, da, ps</li>
                        <li><i class="fas fa-check text-success"></i> Must be unique in the system</li>
                        <li><i class="fas fa-check text-success"></i> Lowercase letters only</li>
                    </ul>
                    
                    <hr>
                    
                    <h6><?php echo __('text_direction'); ?></h6>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-arrow-right text-primary"></i> <strong>LTR:</strong> English, French, Spanish</li>
                        <li><i class="fas fa-arrow-left text-info"></i> <strong>RTL:</strong> Arabic, Persian, Pashto</li>
                    </ul>
                    
                    <hr>
                    
                    <h6><?php echo __('examples'); ?></h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th><?php echo __('code'); ?></th>
                                    <th><?php echo __('name'); ?></th>
                                    <th><?php echo __('native'); ?></th>
                                    <th><?php echo __('direction'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>en</td>
                                    <td>English</td>
                                    <td>English</td>
                                    <td>LTR</td>
                                </tr>
                                <tr>
                                    <td>da</td>
                                    <td>Dari</td>
                                    <td>دری</td>
                                    <td>RTL</td>
                                </tr>
                                <tr>
                                    <td>ps</td>
                                    <td>Pashto</td>
                                    <td>پښتو</td>
                                    <td>RTL</td>
                                </tr>
                                <tr>
                                    <td>fr</td>
                                    <td>French</td>
                                    <td>Français</td>
                                    <td>LTR</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('next_steps'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <a href="translations.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-language"></i> <?php echo __('add_translations'); ?>
                        </a>
                        <a href="import.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-upload"></i> <?php echo __('import_translations'); ?>
                        </a>
                        <a href="index.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-list"></i> <?php echo __('view_all_languages'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Auto-generate language code from name
    $('#language_name').on('input', function() {
        var name = $(this).val().toLowerCase();
        var code = name.substring(0, 2);
        if (code.length === 2) {
            $('#language_code').val(code);
        }
    });
    
    // Validate language code
    $('#language_code').on('input', function() {
        var code = $(this).val();
        if (code.length < 2) {
            $(this).addClass('is-invalid');
        } else {
            $(this).removeClass('is-invalid');
        }
    });
});
</script>

<?php require_once '../../../includes/footer.php'; ?>