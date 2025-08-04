<?php
// Test language switching on public pages (no company_id)
session_start();

require_once 'config/config.php';
require_once 'config/database.php';

echo "<h1>Public Language Switching Test</h1>";

// Simulate public page (no logged in user)
unset($_SESSION['user_id']);
unset($_SESSION['company_id']);

echo "<h2>Current Session Language: " . ($_SESSION['current_language'] ?? 'Not set') . "</h2>";

// Test translations without company_id
echo "<h2>Test Translations (No Company ID):</h2>";
echo "Dashboard: " . __('dashboard') . "<br>";
echo "Employees: " . __('employees') . "<br>";
echo "Settings: " . __('settings') . "<br>";

// Test language switching
echo "<h2>Test Language Switching:</h2>";
echo "<p>Current language: " . getCurrentLanguageInfo()['language_name'] . "</p>";

// Simulate changing to Pashto
$_SESSION['current_language'] = 2;
echo "<p>After switching to Pashto: " . getCurrentLanguageInfo()['language_name'] . "</p>";

// Test translations after language change
echo "<h2>Test Translations After Language Change:</h2>";
echo "Dashboard: " . __('dashboard') . "<br>";
echo "Employees: " . __('employees') . "<br>";
echo "Settings: " . __('settings') . "<br>";

// Reset to English
$_SESSION['current_language'] = 1;
echo "<h2>Reset to English:</h2>";
echo "Dashboard: " . __('dashboard') . "<br>";
echo "Employees: " . __('employees') . "<br>";
echo "Settings: " . __('settings') . "<br>";

echo "<h2>Test Complete!</h2>";
echo "<p>Language switching should work on public pages without company_id.</p>";
?>