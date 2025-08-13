<?php
require_once 'config/config.php';
require_once 'config/database.php';

// Test the comprehensive language system
echo "<h1>Comprehensive Language System Test</h1>";

// Test 1: Basic translation
echo "<h2>Test 1: Basic Translation</h2>";
echo "Dashboard: " . __('dashboard') . "<br>";
echo "Employees: " . __('employees') . "<br>";
echo "Machines: " . __('machines') . "<br>";
echo "Settings: " . __('settings') . "<br>";

// Test 2: Translation with parameters
echo "<h2>Test 2: Translation with Parameters</h2>";
echo "Cannot delete plan: " . __('cannot_delete_plan_in_use', ['count' => 5]) . "<br>";
echo "Field required: " . __('field_required', ['field' => 'Name']) . "<br>";

// Test 3: Fallback translation
echo "<h2>Test 3: Fallback Translation</h2>";
echo "Non-existent key: " . __('non_existent_key') . "<br>";

// Test 4: Alert messages
echo "<h2>Test 4: Alert Messages</h2>";
echo getAlertMessage('success', 'language_changed_successfully') . "<br>";
echo getAlertMessage('error', 'invalid_language') . "<br>";
echo getAlertMessage('warning', 'plan_code_already_exists') . "<br>";
echo getAlertMessage('info', 'please_fill_required_fields') . "<br>";

// Test 5: Button texts
echo "<h2>Test 5: Button Texts</h2>";
echo "Save button: " . getButtonText('save') . "<br>";
echo "Edit button: " . getButtonText('edit') . "<br>";
echo "Delete button: " . getButtonText('delete') . "<br>";

// Test 6: Form labels
echo "<h2>Test 6: Form Labels</h2>";
echo "Name label: " . getFormLabel('name') . "<br>";
echo "Email label: " . getFormLabel('email') . "<br>";
echo "Password label: " . getFormLabel('password') . "<br>";

// Test 7: Table headers
echo "<h2>Test 7: Table Headers</h2>";
echo "Status header: " . getTableHeader('status') . "<br>";
echo "Actions header: " . getTableHeader('actions') . "<br>";
echo "Date header: " . getTableHeader('date') . "<br>";

// Test 8: Page titles
echo "<h2>Test 8: Page Titles</h2>";
echo "Dashboard title: " . getPageTitle('dashboard') . "<br>";
echo "Settings title: " . getPageTitle('settings') . "<br>";

// Test 9: Navigation texts
echo "<h2>Test 9: Navigation Texts</h2>";
echo "Dashboard nav: " . getNavText('dashboard') . "<br>";
echo "Profile nav: " . getNavText('profile') . "<br>";

// Test 10: Sidebar texts
echo "<h2>Test 10: Sidebar Texts</h2>";
echo "Dashboard sidebar: " . getSidebarText('dashboard') . "<br>";
echo "Reports sidebar: " . getSidebarText('reports') . "<br>";

// Test 11: Available languages
echo "<h2>Test 11: Available Languages</h2>";
$languages = getAvailableLanguages();
foreach ($languages as $lang) {
    echo "Language: " . $lang['language_name'] . " (" . $lang['language_code'] . ")<br>";
}

// Test 12: Current language info
echo "<h2>Test 12: Current Language Info</h2>";
$current_lang = getCurrentLanguageInfo();
echo "Current language: " . $current_lang['language_name'] . " (" . $current_lang['language_code'] . ")<br>";

// Test 13: Change language function
echo "<h2>Test 13: Change Language Function</h2>";
echo "Before change: " . getCurrentLanguageInfo()['language_name'] . "<br>";
// changeLanguage(2); // Uncomment to test language change
echo "After change: " . getCurrentLanguageInfo()['language_name'] . "<br>";

echo "<h2>Language System Test Complete!</h2>";
echo "<p>The comprehensive language system is now implemented and ready for use.</p>";
?>