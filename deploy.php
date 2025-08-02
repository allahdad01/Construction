<?php
/**
 * Deployment Script for Construction SaaS Platform
 * This script sets up the database schema and initial data
 */

require_once 'config/config.php';
require_once 'config/database.php';

class Deployment {
    private $db;
    private $conn;
    private $errors = [];
    private $success = [];

    public function __construct() {
        $this->db = new Database();
        try {
            $this->conn = $this->db->getConnection();
            $this->success[] = "Database connection established successfully.";
        } catch (Exception $e) {
            $this->errors[] = "Database connection failed: " . $e->getMessage();
        }
    }

    public function run() {
        echo "🚀 Starting Construction SaaS Platform Deployment...\n\n";

        if (!empty($this->errors)) {
            echo "❌ Deployment failed due to database connection issues:\n";
            foreach ($this->errors as $error) {
                echo "   - $error\n";
            }
            return false;
        }

        $steps = [
            'createTables' => 'Creating database tables',
            'insertInitialData' => 'Inserting initial data',
            'createSuperAdmin' => 'Creating super admin user',
            'setupSampleData' => 'Setting up sample data',
            'verifyDeployment' => 'Verifying deployment'
        ];

        foreach ($steps as $method => $description) {
            echo "📋 $description...\n";
            if ($this->$method()) {
                echo "✅ $description completed successfully.\n";
            } else {
                echo "❌ $description failed.\n";
                return false;
            }
        }

        echo "\n🎉 Deployment completed successfully!\n";
        echo "🌐 Your application is now ready at: " . APP_URL . "\n";
        echo "👤 Super Admin Login:\n";
        echo "   Email: superadmin@construction.com\n";
        echo "   Password: admin123\n\n";
        
        return true;
    }

    private function createTables() {
        try {
            $schema = file_get_contents('database/schema.sql');
            $statements = explode(';', $schema);
            
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement)) {
                    $this->conn->exec($statement);
                }
            }
            
            return true;
        } catch (Exception $e) {
            $this->errors[] = "Failed to create tables: " . $e->getMessage();
            return false;
        }
    }

    private function insertInitialData() {
        try {
            $sampleData = file_get_contents('database/sample_data.sql');
            $statements = explode(';', $sampleData);
            
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement)) {
                    $this->conn->exec($statement);
                }
            }
            
            return true;
        } catch (Exception $e) {
            $this->errors[] = "Failed to insert initial data: " . $e->getMessage();
            return false;
        }
    }

    private function createSuperAdmin() {
        try {
            // Check if super admin already exists
            $stmt = $this->conn->prepare("SELECT id FROM users WHERE email = 'superadmin@construction.com'");
            $stmt->execute();
            
            if ($stmt->fetch()) {
                return true; // Super admin already exists
            }

            // Create super admin user
            $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $this->conn->prepare("
                INSERT INTO users (email, password, first_name, last_name, role, is_active, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                'superadmin@construction.com',
                $hashedPassword,
                'Super',
                'Admin',
                'super_admin',
                1
            ]);
            
            return true;
        } catch (Exception $e) {
            $this->errors[] = "Failed to create super admin: " . $e->getMessage();
            return false;
        }
    }

    private function setupSampleData() {
        try {
            // This method can be extended to add more sample data
            // For now, the sample data is already included in the schema
            return true;
        } catch (Exception $e) {
            $this->errors[] = "Failed to setup sample data: " . $e->getMessage();
            return false;
        }
    }

    private function verifyDeployment() {
        try {
            // Check if essential tables exist
            $tables = ['users', 'companies', 'employees', 'contracts', 'languages'];
            
            foreach ($tables as $table) {
                $stmt = $this->conn->prepare("SHOW TABLES LIKE ?");
                $stmt->execute([$table]);
                if (!$stmt->fetch()) {
                    $this->errors[] = "Table '$table' not found.";
                    return false;
                }
            }
            
            // Check if super admin exists
            $stmt = $this->conn->prepare("SELECT id FROM users WHERE role = 'super_admin'");
            $stmt->execute();
            if (!$stmt->fetch()) {
                $this->errors[] = "Super admin user not found.";
                return false;
            }
            
            // Check if languages exist
            $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM languages");
            $stmt->execute();
            $result = $stmt->fetch();
            if ($result['count'] < 3) {
                $this->errors[] = "Languages not properly set up.";
                return false;
            }
            
            return true;
        } catch (Exception $e) {
            $this->errors[] = "Verification failed: " . $e->getMessage();
            return false;
        }
    }

    public function getErrors() {
        return $this->errors;
    }

    public function getSuccess() {
        return $this->success;
    }
}

// Run deployment if called directly
if (php_sapi_name() === 'cli' || isset($_GET['deploy'])) {
    $deployment = new Deployment();
    $deployment->run();
}
?>