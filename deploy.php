<?php
/**
 * Deployment Script for Construction SaaS Platform
 * This script sets up the database schema and initial data for PostgreSQL
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
            if (!$schema) {
                $this->errors[] = "Could not read schema.sql file";
                return false;
            }

            // Split the schema into individual statements
            $statements = array_filter(array_map('trim', explode(';', $schema)));
            
            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    $this->conn->exec($statement);
                }
            }
            
            $this->success[] = "Database tables created successfully.";
            return true;
        } catch (Exception $e) {
            $this->errors[] = "Error creating tables: " . $e->getMessage();
            return false;
        }
    }

    private function insertInitialData() {
        try {
            $sampleData = file_get_contents('database/sample_data.sql');
            if (!$sampleData) {
                $this->errors[] = "Could not read sample_data.sql file";
                return false;
            }

            // Split the sample data into individual statements
            $statements = array_filter(array_map('trim', explode(';', $sampleData)));
            
            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    $this->conn->exec($statement);
                }
            }
            
            $this->success[] = "Initial data inserted successfully.";
            return true;
        } catch (Exception $e) {
            $this->errors[] = "Error inserting initial data: " . $e->getMessage();
            return false;
        }
    }

    private function createSuperAdmin() {
        try {
            // Check if super admin already exists
            $stmt = $this->conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute(['superadmin@construction.com']);
            
            if ($stmt->fetch()) {
                $this->success[] = "Super admin user already exists.";
                return true;
            }

            // Create super admin user
            $password = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $this->conn->prepare("
                INSERT INTO users (email, password, first_name, last_name, role, is_active) 
                VALUES (?, ?, 'Super', 'Admin', 'super_admin', TRUE)
            ");
            
            if ($stmt->execute(['superadmin@construction.com', $password])) {
                $this->success[] = "Super admin user created successfully.";
                return true;
            } else {
                $this->errors[] = "Failed to create super admin user.";
                return false;
            }
        } catch (Exception $e) {
            $this->errors[] = "Error creating super admin: " . $e->getMessage();
            return false;
        }
    }

    private function setupSampleData() {
        try {
            // Create sample companies if they don't exist
            $companies = [
                [
                    'company_code' => 'ABC001',
                    'company_name' => 'ABC Construction',
                    'email' => 'admin@abc-construction.com',
                    'phone' => '+1-555-0101'
                ],
                [
                    'company_code' => 'XYZ002',
                    'company_name' => 'XYZ Builders',
                    'email' => 'admin@xyz-builders.com',
                    'phone' => '+1-555-0102'
                ]
            ];

            foreach ($companies as $company) {
                $stmt = $this->conn->prepare("
                    INSERT INTO companies (company_code, company_name, email, phone) 
                    VALUES (?, ?, ?, ?)
                    ON CONFLICT (company_code) DO NOTHING
                ");
                $stmt->execute([
                    $company['company_code'],
                    $company['company_name'],
                    $company['email'],
                    $company['phone']
                ]);
            }

            $this->success[] = "Sample data setup completed.";
            return true;
        } catch (Exception $e) {
            $this->errors[] = "Error setting up sample data: " . $e->getMessage();
            return false;
        }
    }

    private function verifyDeployment() {
        try {
            // Check if essential tables exist
            $tables = ['companies', 'users', 'employees', 'machines', 'contracts'];
            
            foreach ($tables as $table) {
                $stmt = $this->conn->prepare("SELECT COUNT(*) FROM $table");
                $stmt->execute();
                $count = $stmt->fetchColumn();
                
                if ($count === false) {
                    $this->errors[] = "Table $table does not exist or is not accessible.";
                    return false;
                }
            }

            // Check if super admin exists
            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM users WHERE role = 'super_admin'");
            $stmt->execute();
            $adminCount = $stmt->fetchColumn();
            
            if ($adminCount == 0) {
                $this->errors[] = "Super admin user not found.";
                return false;
            }

            $this->success[] = "Deployment verification completed successfully.";
            return true;
        } catch (Exception $e) {
            $this->errors[] = "Error during verification: " . $e->getMessage();
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