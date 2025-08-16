<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set no cache headers
setNoCacheHeaders();

$db = new Database();
$conn = $db->getConnection();

// Get available languages
$stmt = $conn->prepare("SELECT * FROM languages WHERE is_active = 1 ORDER BY language_name");
$stmt->execute();
$available_languages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current language
$current_language = getCompanyLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $current_language['language_code'] ?? 'en'; ?>" dir="<?php echo isRTL() ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Language Switching Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { padding: 2rem; background-color: #f8f9fa; }
        .language-card { transition: transform 0.2s; }
        .language-card:hover { transform: translateY(-5px); }
        .current-language { border: 3px solid #28a745; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">
                            <i class="fas fa-language me-2"></i>
                            Language Switching Test
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Current Language:</strong> 
                            <?php echo htmlspecialchars($current_language['language_name_native'] ?? 'English'); ?>
                            (<?php echo htmlspecialchars($current_language['language_code'] ?? 'en'); ?>)
                        </div>

                        <h5 class="mb-3">Available Languages:</h5>
                        <div class="row">
                            <?php foreach ($available_languages as $lang): ?>
                                <?php $is_current = ($current_language['id'] == $lang['id']); ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card language-card <?php echo $is_current ? 'current-language' : ''; ?>">
                                        <div class="card-body text-center">
                                            <h6 class="card-title">
                                                <i class="fas fa-flag me-2"></i>
                                                <?php echo htmlspecialchars($lang['language_name_native']); ?>
                                            </h6>
                                            <p class="card-text text-muted">
                                                <?php echo htmlspecialchars($lang['language_name']); ?>
                                            </p>
                                            <?php if ($is_current): ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check me-1"></i>Current
                                                </span>
                                            <?php else: ?>
                                                <button class="btn btn-primary btn-sm" 
                                                        onclick="changeLanguage('<?php echo $lang['language_code']; ?>')">
                                                    <i class="fas fa-exchange-alt me-1"></i>Switch to
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <hr>

                        <div class="text-center">
                            <a href="../dashboard/" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function changeLanguage(languageCode) {
            // Show loading indicator
            const loadingToast = document.createElement('div');
            loadingToast.className = 'alert alert-info alert-dismissible fade show position-fixed';
            loadingToast.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
            loadingToast.innerHTML = `
                <i class="fas fa-spinner fa-spin me-2"></i>
                Changing language to ${languageCode.toUpperCase()}...
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(loadingToast);

            // Make API call to change language
            fetch('../api/change-language.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    language: languageCode
                })
            })
            .then(response => response.json())
            .then(data => {
                // Remove loading indicator
                loadingToast.remove();

                if (data.success) {
                    // Show success message
                    const successToast = document.createElement('div');
                    successToast.className = 'alert alert-success alert-dismissible fade show position-fixed';
                    successToast.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
                    successToast.innerHTML = `
                        <i class="fas fa-check-circle me-2"></i>
                        ${data.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    document.body.appendChild(successToast);

                    // Reload page to apply language changes
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    // Show error message
                    const errorToast = document.createElement('div');
                    errorToast.className = 'alert alert-danger alert-dismissible fade show position-fixed';
                    errorToast.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
                    errorToast.innerHTML = `
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        ${data.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    document.body.appendChild(errorToast);
                }
            })
            .catch(error => {
                // Remove loading indicator
                loadingToast.remove();

                // Show error message
                const errorToast = document.createElement('div');
                errorToast.className = 'alert alert-danger alert-dismissible fade show position-fixed';
                errorToast.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
                errorToast.innerHTML = `
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Failed to change language. Please try again.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                document.body.appendChild(errorToast);
            });
        }
    </script>
</body>
</html>