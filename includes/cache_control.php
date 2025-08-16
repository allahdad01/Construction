<?php
/**
 * Cache Control Utilities
 * Prevents caching of dynamic content and ensures fresh data display
 */

/**
 * Set headers to prevent caching of dynamic content
 */
function setNoCacheHeaders() {
    header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

/**
 * Set headers for short-term caching (useful for data that changes occasionally)
 * @param int $seconds Number of seconds to cache
 */
function setShortCacheHeaders($seconds = 60) {
    header('Cache-Control: public, max-age=' . $seconds);
    header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + $seconds));
}

/**
 * Set headers for static assets that can be cached longer
 * @param int $seconds Number of seconds to cache
 */
function setLongCacheHeaders($seconds = 86400) { // Default: 24 hours
    header('Cache-Control: public, max-age=' . $seconds);
    header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + $seconds));
}

/**
 * Add cache busting parameter to URLs
 * @param string $url The URL to add cache busting to
 * @return string URL with cache busting parameter
 */
function addCacheBuster($url) {
    $separator = strpos($url, '?') !== false ? '&' : '?';
    return $url . $separator . 'v=' . time();
}

/**
 * Check if request is for dynamic content
 * @return bool True if dynamic content
 */
function isDynamicRequest() {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $path = $_SERVER['REQUEST_URI'] ?? '';
    
    // Check if it's a PHP file or has query parameters
    if (strpos($script, '.php') !== false || strpos($path, '?') !== false) {
        return true;
    }
    
    // Check if it's an API endpoint or admin area
    $dynamic_patterns = [
        '/api/',
        '/dashboard/',
        '/users/',
        '/admin/',
        '/employee/',
        '/super-admin/',
        '/reports/',
        '/settings/'
    ];
    
    foreach ($dynamic_patterns as $pattern) {
        if (strpos($path, $pattern) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * Automatically set appropriate cache headers based on request type
 */
function setAutoCacheHeaders() {
    if (isDynamicRequest()) {
        setNoCacheHeaders();
    } else {
        // For static assets, allow longer caching
        setLongCacheHeaders();
    }
}
?>