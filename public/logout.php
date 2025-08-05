<?php
require_once '../config/config.php';

// Clear remember me token if exists
if (isset($_COOKIE['remember_token'])) {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Delete token from database
    $stmt = $conn->prepare("DELETE FROM remember_tokens WHERE token = ?");
    $stmt->execute([$_COOKIE['remember_token']]);
    
    // Delete cookie
    setcookie('remember_token', '', time() - 3600, '/');
}

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: login.php');
exit();
?>