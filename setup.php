<?php
require_once 'config/config.php';

// Check if already set up
if (file_exists('config/database.php')) {
    echo "System appears to be already set up. If you need to reset, please delete config/database.php and try again.\n";
    exit();
}

echo "=== Construction Company Multi-Tenant SaaS Platform Setup ===\n\n";

// Database configuration
echo "Please provide your database configuration:\n";
echo "Database Host (default: localhost): ";
$host = trim(fgets(STDIN)) ?: 'localhost';

echo "Database Name: ";
$database = trim(fgets(STDIN));
if (empty($database)) {
    echo "Database name is required!\n";
    exit();
}

echo "Database Username: ";
$username = trim(fgets(STDIN));
if (empty($username)) {
    echo "Database username is required!\n";
    exit();
}

echo "Database Password: ";
$password = trim(fgets(STDIN));

echo "Database Port (default: 3306): ";
$port = trim(fgets(STDIN)) ?: '3306';

// Test database connection
try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$database", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "\n✓ Database connection successful!\n";
} catch (PDOException $e) {
    echo "\n✗ Database connection failed: " . $e->getMessage() . "\n";
    exit();
}

// Create database configuration file
$config_content = "<?php
class Database {
    private \$host = '$host';
    private \$database = '$database';
    private \$username = '$username';
    private \$password = '$password';
    private \$port = '$port';
    private \$conn = null;

    public function getConnection() {
        if (\$this->conn === null) {
            try {
                \$this->conn = new PDO(
                    'mysql:host=' . \$this->host . ';port=' . \$this->port . ';dbname=' . \$this->database,
                    \$this->username,
                    \$this->password
                );
                \$this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException \$e) {
                throw new Exception('Database connection failed: ' . \$e->getMessage());
            }
        }
        return \$this->conn;
    }
}
?>";

if (file_put_contents('config/database.php', $config_content)) {
    echo "✓ Database configuration file created!\n";
} else {
    echo "✗ Failed to create database configuration file!\n";
    exit();
}

// Import database schema
echo "\nImporting database schema...\n";
$schema_file = 'database/schema.sql';
if (file_exists($schema_file)) {
    $schema = file_get_contents($schema_file);
    try {
        $pdo->exec($schema);
        echo "✓ Database schema imported successfully!\n";
    } catch (PDOException $e) {
        echo "✗ Failed to import schema: " . $e->getMessage() . "\n";
        exit();
    }
} else {
    echo "✗ Schema file not found: $schema_file\n";
    exit();
}

// Import sample data
echo "\nImporting sample data...\n";
$sample_file = 'database/sample_data.sql';
if (file_exists($sample_file)) {
    $sample_data = file_get_contents($sample_file);
    try {
        $pdo->exec($sample_data);
        echo "✓ Sample data imported successfully!\n";
    } catch (PDOException $e) {
        echo "✗ Failed to import sample data: " . $e->getMessage() . "\n";
        exit();
    }
} else {
    echo "⚠ Sample data file not found: $sample_file\n";
}

// Create .htaccess file for security
$htaccess_content = "RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection \"1; mode=block\"
Header always set Referrer-Policy \"strict-origin-when-cross-origin\"

# Prevent access to sensitive files
<Files \"*.sql\">
    Order allow,deny
    Deny from all
</Files>

<Files \"*.log\">
    Order allow,deny
    Deny from all
</Files>

<Files \"config/*\">
    Order allow,deny
    Deny from all
</Files>";

if (file_put_contents('public/.htaccess', $htaccess_content)) {
    echo "✓ Security .htaccess file created!\n";
} else {
    echo "⚠ Failed to create .htaccess file!\n";
}

echo "\n=== Setup Complete! ===\n\n";
echo "Your Construction Company Multi-Tenant SaaS Platform is now ready!\n\n";
echo "Default login credentials:\n";
echo "Super Admin: admin@construction-saas.com / password\n";
echo "Company Admin: admin@abc-construction.com / password\n";
echo "Driver: driver1@abc-construction.com / password\n\n";
echo "Access your application at: http://your-domain.com/public/\n";
echo "Make sure to change the default passwords after first login!\n\n";
echo "For security, please:\n";
echo "1. Change default passwords\n";
echo "2. Configure your web server properly\n";
echo "3. Set up SSL certificate\n";
echo "4. Regular backups of your database\n";
?>