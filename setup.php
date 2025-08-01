<?php
/**
 * Construction Company SaaS Platform Setup Script
 * Run this script to check system requirements and set up the application
 */

echo "==========================================\n";
echo "Construction Company SaaS Platform Setup\n";
echo "==========================================\n\n";

// Check PHP version
echo "Checking PHP version...\n";
if (version_compare(PHP_VERSION, '7.4.0', '>=')) {
    echo "✓ PHP version " . PHP_VERSION . " is supported\n";
} else {
    echo "✗ PHP version " . PHP_VERSION . " is not supported. Please upgrade to PHP 7.4 or higher.\n";
    exit(1);
}

// Check required PHP extensions
echo "\nChecking PHP extensions...\n";
$required_extensions = ['pdo', 'pdo_mysql', 'json', 'mbstring'];
$missing_extensions = [];

foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✓ $ext extension is loaded\n";
    } else {
        echo "✗ $ext extension is missing\n";
        $missing_extensions[] = $ext;
    }
}

if (!empty($missing_extensions)) {
    echo "\nPlease install the missing extensions:\n";
    foreach ($missing_extensions as $ext) {
        echo "- $ext\n";
    }
    exit(1);
}

// Check if config files exist
echo "\nChecking configuration files...\n";
$config_files = [
    'config/config.php' => 'Main configuration file',
    'config/database.php' => 'Database configuration file',
    'database/schema.sql' => 'Database schema file'
];

foreach ($config_files as $file => $description) {
    if (file_exists($file)) {
        echo "✓ $description exists\n";
    } else {
        echo "✗ $description is missing\n";
    }
}

// Check if directories exist
echo "\nChecking directory structure...\n";
$directories = [
    'public' => 'Public web directory',
    'includes' => 'Include files directory',
    'config' => 'Configuration directory',
    'database' => 'Database files directory'
];

foreach ($directories as $dir => $description) {
    if (is_dir($dir)) {
        echo "✓ $description exists\n";
    } else {
        echo "✗ $description is missing\n";
    }
}

// Test database connection
echo "\nTesting database connection...\n";
try {
    require_once 'config/database.php';
    $db = new Database();
    $conn = $db->getConnection();
    
    if ($conn) {
        echo "✓ Database connection successful\n";
        
        // Check if tables exist
        $stmt = $conn->prepare("SHOW TABLES");
        $stmt->execute();
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $required_tables = [
            'employees', 'machines', 'projects', 'contracts', 
            'working_hours', 'parking_spaces', 'parking_rentals',
            'rental_areas', 'area_rentals', 'expenses', 
            'salary_payments', 'users'
        ];
        
        $missing_tables = array_diff($required_tables, $tables);
        
        if (empty($missing_tables)) {
            echo "✓ All required database tables exist\n";
        } else {
            echo "✗ Missing database tables:\n";
            foreach ($missing_tables as $table) {
                echo "  - $table\n";
            }
            echo "\nPlease import the database schema from database/schema.sql\n";
        }
        
    } else {
        echo "✗ Database connection failed\n";
    }
} catch (Exception $e) {
    echo "✗ Database connection error: " . $e->getMessage() . "\n";
    echo "Please check your database configuration in config/database.php\n";
}

// Check file permissions
echo "\nChecking file permissions...\n";
$writable_dirs = ['public', 'config'];
foreach ($writable_dirs as $dir) {
    if (is_writable($dir)) {
        echo "✓ $dir directory is writable\n";
    } else {
        echo "✗ $dir directory is not writable\n";
    }
}

// Create .htaccess file if it doesn't exist
echo "\nSetting up .htaccess file...\n";
$htaccess_content = "RewriteEngine On\n";
$htaccess_content .= "RewriteCond %{REQUEST_FILENAME} !-f\n";
$htaccess_content .= "RewriteCond %{REQUEST_FILENAME} !-d\n";
$htaccess_content .= "RewriteRule ^(.*)$ index.php [QSA,L]\n\n";
$htaccess_content .= "# Security headers\n";
$htaccess_content .= "Header always set X-Content-Type-Options nosniff\n";
$htaccess_content .= "Header always set X-Frame-Options DENY\n";
$htaccess_content .= "Header always set X-XSS-Protection \"1; mode=block\"\n";

if (file_put_contents('public/.htaccess', $htaccess_content)) {
    echo "✓ .htaccess file created successfully\n";
} else {
    echo "✗ Failed to create .htaccess file\n";
}

// Display setup summary
echo "\n==========================================\n";
echo "Setup Summary\n";
echo "==========================================\n";
echo "If all checks passed, your application should be ready to use.\n\n";

echo "Next steps:\n";
echo "1. Access the application at: http://your-domain.com/public/\n";
echo "2. Login with default credentials:\n";
echo "   Username: admin\n";
echo "   Password: password\n";
echo "3. Change the default password immediately\n";
echo "4. Configure your web server to point to the 'public' directory\n\n";

echo "For detailed installation instructions, see INSTALL.md\n";
echo "For troubleshooting, check the error logs of your web server\n\n";

echo "Setup completed!\n";
?>