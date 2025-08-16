<?php
/**
 * Cache Clearing Utility
 * Clears all caches to ensure fresh data display
 */

require_once '../config/config.php';
require_once '../config/database.php';

// Check if user is authenticated and is admin
requireAuth();

if (!isSuperAdmin() && !isCompanyAdmin()) {
    header('Location: ../unauthorized.php');
    exit;
}

// Clear browser cache headers
setNoCacheHeaders();

// Clear service worker cache via JavaScript
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clear Cache - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-broom me-2"></i>
                            Clear Application Cache
                        </h4>
                    </div>
                    <div class="card-body">
                        <div id="cache-status" class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Click the button below to clear all caches and ensure fresh data display.
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button id="clear-cache-btn" class="btn btn-warning btn-lg">
                                <i class="fas fa-broom me-2"></i>
                                Clear All Caches
                            </button>
                            
                            <a href="../dashboard/" class="btn btn-primary">
                                <i class="fas fa-arrow-left me-2"></i>
                                Back to Dashboard
                            </a>
                        </div>
                        
                        <div id="cache-results" class="mt-4" style="display: none;">
                            <h5>Cache Clearing Results:</h5>
                            <ul id="results-list" class="list-group">
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('clear-cache-btn').addEventListener('click', async function() {
            const btn = this;
            const status = document.getElementById('cache-status');
            const results = document.getElementById('cache-results');
            const resultsList = document.getElementById('results-list');
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Clearing Caches...';
            status.className = 'alert alert-warning';
            status.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Clearing caches, please wait...';
            
            const resultsData = [];
            
            try {
                // Clear service worker cache
                if ('serviceWorker' in navigator) {
                    try {
                        const registrations = await navigator.serviceWorker.getRegistrations();
                        for (let registration of registrations) {
                            await registration.unregister();
                            resultsData.push('✓ Service Worker unregistered');
                        }
                        
                        // Clear all caches
                        const cacheNames = await caches.keys();
                        for (let cacheName of cacheNames) {
                            await caches.delete(cacheName);
                            resultsData.push(`✓ Cache "${cacheName}" cleared`);
                        }
                    } catch (e) {
                        resultsData.push(`⚠ Service Worker cache: ${e.message}`);
                    }
                } else {
                    resultsData.push('ℹ Service Worker not supported');
                }
                
                // Clear localStorage and sessionStorage
                try {
                    localStorage.clear();
                    sessionStorage.clear();
                    resultsData.push('✓ Browser storage cleared');
                } catch (e) {
                    resultsData.push(`⚠ Browser storage: ${e.message}`);
                }
                
                // Clear cookies (except session cookies)
                try {
                    const cookies = document.cookie.split(';');
                    for (let cookie of cookies) {
                        const eqPos = cookie.indexOf('=');
                        const name = eqPos > -1 ? cookie.substr(0, eqPos) : cookie;
                        if (name.trim() !== 'PHPSESSID') {
                            document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/';
                        }
                    }
                    resultsData.push('✓ Cookies cleared (except session)');
                } catch (e) {
                    resultsData.push(`⚠ Cookies: ${e.message}`);
                }
                
                // Force page reload to ensure fresh data
                setTimeout(() => {
                    status.className = 'alert alert-success';
                    status.innerHTML = '<i class="fas fa-check-circle me-2"></i>All caches cleared successfully! Page will reload in 3 seconds...';
                    
                    // Show results
                    results.style.display = 'block';
                    resultsList.innerHTML = resultsData.map(result => 
                        `<li class="list-group-item">${result}</li>`
                    ).join('');
                    
                    // Reload page
                    setTimeout(() => {
                        window.location.reload(true);
                    }, 3000);
                }, 1000);
                
            } catch (error) {
                status.className = 'alert alert-danger';
                status.innerHTML = `<i class="fas fa-exclamation-triangle me-2"></i>Error clearing caches: ${error.message}`;
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-broom me-2"></i>Clear All Caches';
            }
        });
    </script>
</body>
</html>