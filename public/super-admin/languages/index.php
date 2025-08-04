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

// Handle language activation/deactivation
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $language_id = (int)$_GET['id'];
    $action = $_GET['toggle'];
    
    if ($action === 'activate' || $action === 'deactivate') {
        $is_active = $action === 'activate' ? 1 : 0;
        $stmt = $conn->prepare("UPDATE languages SET is_active = ? WHERE id = ?");
        if ($stmt->execute([$is_active, $language_id])) {
            $success = __('language_action_successful', ['action' => $action]);
        } else {
            $error = __('language_action_failed', ['action' => $action]);
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
        <h1 class="h3 mb-0 text-gray-800"><?php echo __('language_management'); ?></h1>
        <a href="add.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> <?php echo __('add_new_language'); ?>
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

    <!-- Statistics Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                <?php echo __('total_languages'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($languages); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-language fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                <?php echo __('active_languages'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo count(array_filter($languages, function($lang) { return $lang['is_active']; })); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                <?php echo __('rtl_languages'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo count(array_filter($languages, function($lang) { return $lang['direction'] === 'rtl'; })); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-text-width fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                <?php echo __('default_language'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php 
                                $default_lang = array_filter($languages, function($lang) { return $lang['is_default']; });
                                echo $default_lang ? reset($default_lang)['language_name'] : __('none');
                                ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-star fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Languages Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary"><?php echo __('languages'); ?></h6>
        </div>
        <div class="card-body">
            <?php if (empty($languages)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-language fa-3x text-gray-300 mb-3"></i>
                    <p class="text-gray-500"><?php echo __('no_languages_found'); ?></p>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> <?php echo __('add_first_language'); ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th><?php echo __('language'); ?></th>
                                <th><?php echo __('code'); ?></th>
                                <th><?php echo __('direction'); ?></th>
                                <th><?php echo __('status'); ?></th>
                                <th><?php echo __('translations'); ?></th>
                                <th><?php echo __('actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($languages as $language): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($language['language_name_native']); ?></strong>
                                            <br><small class="text-muted">
                                                <?php echo htmlspecialchars($language['language_name']); ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo htmlspecialchars($language['language_code']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $language['direction'] === 'rtl' ? 'bg-info' : 'bg-primary'; ?>">
                                            <?php echo strtoupper($language['direction']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $language['is_active'] ? 'bg-success' : 'bg-secondary'; 
                                        ?>">
                                            <?php echo $language['is_active'] ? __('active') : __('inactive'); ?>
                                        </span>
                                        <?php if ($language['is_default']): ?>
                                            <br><small class="text-muted"><?php echo __('default'); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        // Count translations for this language
                                        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM language_translations WHERE language_id = ?");
                                        $stmt->execute([$language['id']]);
                                        $translation_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                                        ?>
                                        <span class="badge bg-info"><?php echo $translation_count; ?> <?php echo __('translations'); ?></span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="translations.php?language_id=<?php echo $language['id']; ?>" 
                                               class="btn btn-sm btn-info" title="<?php echo __('manage_translations'); ?>">
                                                <i class="fas fa-language"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $language['id']; ?>" 
                                               class="btn btn-sm btn-warning" title="<?php echo __('edit'); ?>">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($language['is_active']): ?>
                                                <a href="?toggle=deactivate&id=<?php echo $language['id']; ?>" 
                                                   class="btn btn-sm btn-secondary" title="<?php echo __('deactivate'); ?>"
                                                   onclick="return confirm('<?php echo __('confirm_deactivate_language'); ?>')">
                                                    <i class="fas fa-eye-slash"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="?toggle=activate&id=<?php echo $language['id']; ?>" 
                                                   class="btn btn-sm btn-success" title="<?php echo __('activate'); ?>">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if (!$language['is_default']): ?>
                                                <a href="delete.php?id=<?php echo $language['id']; ?>" 
                                                   class="btn btn-sm btn-danger" title="<?php echo __('delete'); ?>"
                                                   onclick="return confirm('<?php echo __('confirm_delete_language'); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
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

    <!-- Quick Actions -->
    <div class="row">
        <div class="col-md-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('quick_actions'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <a href="add.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-plus"></i> <?php echo __('add_new_language'); ?>
                        </a>
                        <a href="translations.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-language"></i> <?php echo __('manage_all_translations'); ?>
                        </a>
                        <a href="import.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-upload"></i> <?php echo __('import_translations'); ?>
                        </a>
                        <a href="export.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-download"></i> <?php echo __('export_translations'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('language_statistics'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <h6><?php echo __('direction_breakdown'); ?></h6>
                            <p><strong>LTR:</strong> <?php echo count(array_filter($languages, function($lang) { return $lang['direction'] === 'ltr'; })); ?></p>
                            <p><strong>RTL:</strong> <?php echo count(array_filter($languages, function($lang) { return $lang['direction'] === 'rtl'; })); ?></p>
                        </div>
                        <div class="col-6">
                            <h6><?php echo __('status_breakdown'); ?></h6>
                            <p><strong><?php echo __('active'); ?>:</strong> <?php echo count(array_filter($languages, function($lang) { return $lang['is_active']; })); ?></p>
                            <p><strong><?php echo __('inactive'); ?>:</strong> <?php echo count(array_filter($languages, function($lang) { return !$lang['is_active']; })); ?></p>
                        </div>
                    </div>
                    <hr>
                    <div class="text-center">
                        <h6><?php echo __('translation_coverage'); ?></h6>
                        <small class="text-muted"><?php echo __('average_translations_per_language'); ?></small>
                        <?php 
                        $total_translations = 0;
                        foreach ($languages as $lang) {
                            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM language_translations WHERE language_id = ?");
                            $stmt->execute([$lang['id']]);
                            $total_translations += $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                        }
                        $avg_translations = count($languages) > 0 ? round($total_translations / count($languages)) : 0;
                        ?>
                        <h4 class="text-info"><?php echo $avg_translations; ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#dataTable').DataTable({
        "order": [[0, "asc"]],
        "pageLength": 25
    });
});
</script>

<?php require_once '../../../includes/footer.php'; ?>