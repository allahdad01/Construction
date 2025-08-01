-- Construction Company Multi-Tenant SaaS Platform Database Schema

-- Create database
CREATE DATABASE IF NOT EXISTS construction_saas;
USE construction_saas;

-- Companies table (Tenants)
CREATE TABLE companies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_code VARCHAR(20) UNIQUE NOT NULL,
    company_name VARCHAR(200) NOT NULL,
    domain VARCHAR(100) UNIQUE,
    subdomain VARCHAR(50) UNIQUE,
    contact_person VARCHAR(100),
    contact_email VARCHAR(100) UNIQUE NOT NULL,
    contact_phone VARCHAR(20),
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    country VARCHAR(100) DEFAULT 'USA',
    postal_code VARCHAR(20),
    logo_url VARCHAR(255),
    website VARCHAR(255),
    industry VARCHAR(100),
    employee_count INT DEFAULT 0,
    subscription_plan ENUM('basic', 'professional', 'enterprise') DEFAULT 'basic',
    subscription_status ENUM('active', 'suspended', 'cancelled', 'trial') DEFAULT 'trial',
    trial_ends_at TIMESTAMP NULL,
    subscription_ends_at TIMESTAMP NULL,
    max_employees INT DEFAULT 50,
    max_machines INT DEFAULT 100,
    max_projects INT DEFAULT 50,
    features JSON,
    settings JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Subscription plans table
CREATE TABLE subscription_plans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    plan_name VARCHAR(100) NOT NULL,
    plan_code VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    billing_cycle ENUM('monthly', 'yearly') DEFAULT 'monthly',
    max_employees INT DEFAULT 50,
    max_machines INT DEFAULT 100,
    max_projects INT DEFAULT 50,
    features JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Company payments table
CREATE TABLE company_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    payment_code VARCHAR(20) UNIQUE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    payment_method ENUM('credit_card', 'bank_transfer', 'paypal', 'stripe') DEFAULT 'credit_card',
    payment_status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    billing_period_start DATE,
    billing_period_end DATE,
    subscription_plan VARCHAR(50),
    transaction_id VARCHAR(100),
    payment_date TIMESTAMP NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

-- Users table for system access (Multi-tenant)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NULL,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    phone VARCHAR(20),
    role ENUM('super_admin', 'company_admin', 'driver', 'driver_assistant', 'parking_user', 'area_renter', 'container_renter') NOT NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    last_login TIMESTAMP NULL,
    email_verified_at TIMESTAMP NULL,
    remember_token VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    UNIQUE KEY unique_username_per_company (company_id, username)
);

-- Employees table (Company-specific)
CREATE TABLE employees (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    user_id INT NULL,
    employee_code VARCHAR(20) NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    position ENUM('driver', 'driver_assistant') NOT NULL,
    monthly_salary DECIMAL(10,2) NOT NULL,
    daily_rate DECIMAL(10,2) GENERATED ALWAYS AS (monthly_salary / 30) STORED,
    hire_date DATE NOT NULL,
    status ENUM('active', 'inactive', 'terminated', 'on_leave') DEFAULT 'active',
    termination_date DATE NULL,
    leave_start_date DATE NULL,
    leave_end_date DATE NULL,
    total_leave_days INT DEFAULT 0,
    used_leave_days INT DEFAULT 0,
    remaining_leave_days INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_employee_code_per_company (company_id, employee_code)
);

-- Employee attendance and leave tracking
CREATE TABLE employee_attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    employee_id INT NOT NULL,
    date DATE NOT NULL,
    status ENUM('present', 'absent', 'leave', 'half_day') DEFAULT 'present',
    check_in_time TIME NULL,
    check_out_time TIME NULL,
    working_hours DECIMAL(4,2) DEFAULT 0,
    leave_type ENUM('sick_leave', 'annual_leave', 'personal_leave', 'other') NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attendance_per_day (employee_id, date)
);

-- Machines table (Company-specific)
CREATE TABLE machines (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    machine_code VARCHAR(20) NOT NULL,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(50) NOT NULL,
    model VARCHAR(100),
    year_manufactured INT,
    capacity VARCHAR(50),
    fuel_type ENUM('diesel', 'gasoline', 'electric', 'hybrid'),
    status ENUM('available', 'in_use', 'maintenance', 'retired') DEFAULT 'available',
    purchase_date DATE,
    purchase_cost DECIMAL(10,2),
    current_value DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    UNIQUE KEY unique_machine_code_per_company (company_id, machine_code)
);

-- Projects table (Company-specific)
CREATE TABLE projects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    project_code VARCHAR(20) NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    client_name VARCHAR(100),
    client_contact VARCHAR(100),
    start_date DATE,
    end_date DATE,
    status ENUM('planning', 'active', 'completed', 'cancelled') DEFAULT 'planning',
    total_budget DECIMAL(12,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    UNIQUE KEY unique_project_code_per_company (company_id, project_code)
);

-- Contracts table (Company-specific)
CREATE TABLE contracts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    contract_code VARCHAR(20) NOT NULL,
    project_id INT,
    machine_id INT,
    contract_type ENUM('hourly', 'daily', 'monthly') NOT NULL,
    rate_amount DECIMAL(10,2) NOT NULL,
    total_hours_required INT DEFAULT 0,
    total_days_required INT DEFAULT 0,
    working_hours_per_day INT DEFAULT 9,
    start_date DATE NOT NULL,
    end_date DATE,
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    total_amount DECIMAL(12,2) DEFAULT 0,
    amount_paid DECIMAL(12,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE SET NULL,
    UNIQUE KEY unique_contract_code_per_company (company_id, contract_code)
);

-- Working hours tracking (Company-specific)
CREATE TABLE working_hours (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    contract_id INT,
    machine_id INT,
    employee_id INT,
    date DATE NOT NULL,
    hours_worked DECIMAL(4,2) DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL
);

-- Parking spaces table (Company-specific)
CREATE TABLE parking_spaces (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    space_code VARCHAR(20) NOT NULL,
    space_name VARCHAR(100) NOT NULL,
    space_type ENUM('machine', 'container', 'equipment') NOT NULL,
    size VARCHAR(50),
    monthly_rate DECIMAL(10,2) NOT NULL,
    daily_rate DECIMAL(10,2) GENERATED ALWAYS AS (monthly_rate / 30) STORED,
    status ENUM('available', 'occupied', 'maintenance') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    UNIQUE KEY unique_space_code_per_company (company_id, space_code)
);

-- Parking rentals (Company-specific)
CREATE TABLE parking_rentals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    parking_space_id INT,
    user_id INT,
    rental_code VARCHAR(20) NOT NULL,
    client_name VARCHAR(100) NOT NULL,
    client_contact VARCHAR(100),
    machine_name VARCHAR(100),
    start_date DATE NOT NULL,
    end_date DATE,
    monthly_rate DECIMAL(10,2) NOT NULL,
    daily_rate DECIMAL(10,2) GENERATED ALWAYS AS (monthly_rate / 30) STORED,
    total_days INT DEFAULT 0,
    total_amount DECIMAL(10,2) DEFAULT 0,
    amount_paid DECIMAL(10,2) DEFAULT 0,
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (parking_space_id) REFERENCES parking_spaces(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_rental_code_per_company (company_id, rental_code)
);

-- Area rentals table (Company-specific)
CREATE TABLE rental_areas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    area_code VARCHAR(20) NOT NULL,
    area_name VARCHAR(100) NOT NULL,
    area_type ENUM('storage', 'workshop', 'office', 'other') NOT NULL,
    size VARCHAR(50),
    monthly_rate DECIMAL(10,2) NOT NULL,
    daily_rate DECIMAL(10,2) GENERATED ALWAYS AS (monthly_rate / 30) STORED,
    status ENUM('available', 'occupied', 'maintenance') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    UNIQUE KEY unique_area_code_per_company (company_id, area_code)
);

-- Area rentals (Company-specific)
CREATE TABLE area_rentals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    rental_area_id INT,
    user_id INT,
    rental_code VARCHAR(20) NOT NULL,
    client_name VARCHAR(100) NOT NULL,
    client_contact VARCHAR(100),
    purpose VARCHAR(200),
    start_date DATE NOT NULL,
    end_date DATE,
    monthly_rate DECIMAL(10,2) NOT NULL,
    daily_rate DECIMAL(10,2) GENERATED ALWAYS AS (monthly_rate / 30) STORED,
    total_days INT DEFAULT 0,
    total_amount DECIMAL(10,2) DEFAULT 0,
    amount_paid DECIMAL(10,2) DEFAULT 0,
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (rental_area_id) REFERENCES rental_areas(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_area_rental_code_per_company (company_id, rental_code)
);

-- Expenses table (Company-specific)
CREATE TABLE expenses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    expense_code VARCHAR(20) NOT NULL,
    category ENUM('fuel', 'maintenance', 'salary', 'rent', 'utilities', 'insurance', 'other') NOT NULL,
    description VARCHAR(200) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    expense_date DATE NOT NULL,
    payment_method ENUM('cash', 'bank_transfer', 'check', 'credit_card') DEFAULT 'cash',
    reference_number VARCHAR(50),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    UNIQUE KEY unique_expense_code_per_company (company_id, expense_code)
);

-- Salary payments table (Company-specific)
CREATE TABLE salary_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    payment_code VARCHAR(20) NOT NULL,
    employee_id INT,
    payment_month INT NOT NULL,
    payment_year INT NOT NULL,
    working_days INT DEFAULT 0,
    leave_days INT DEFAULT 0,
    daily_rate DECIMAL(10,2) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    amount_paid DECIMAL(10,2) DEFAULT 0,
    payment_date DATE,
    payment_method ENUM('cash', 'bank_transfer', 'check') DEFAULT 'bank_transfer',
    notes TEXT,
    status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    UNIQUE KEY unique_payment_code_per_company (company_id, payment_code)
);

-- User payments and billing (for parking, area, container renters)
CREATE TABLE user_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    user_id INT NOT NULL,
    payment_code VARCHAR(20) NOT NULL,
    payment_type ENUM('parking_rental', 'area_rental', 'container_rental') NOT NULL,
    rental_id INT,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE,
    payment_method ENUM('cash', 'bank_transfer', 'check', 'credit_card') DEFAULT 'cash',
    status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_payment_code_per_company (company_id, payment_code)
);

-- System settings table
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create indexes for better performance
CREATE INDEX idx_companies_subscription_status ON companies(subscription_status);
CREATE INDEX idx_companies_subscription_ends_at ON companies(subscription_ends_at);
CREATE INDEX idx_users_company_role ON users(company_id, role);
CREATE INDEX idx_employees_company_status ON employees(company_id, status);
CREATE INDEX idx_employees_leave_dates ON employees(leave_start_date, leave_end_date);
CREATE INDEX idx_attendance_employee_date ON employee_attendance(employee_id, date);
CREATE INDEX idx_machines_company_status ON machines(company_id, status);
CREATE INDEX idx_contracts_company_status ON contracts(company_id, status);
CREATE INDEX idx_working_hours_date ON working_hours(date);
CREATE INDEX idx_parking_rentals_status ON parking_rentals(company_id, status);
CREATE INDEX idx_area_rentals_status ON area_rentals(company_id, status);
CREATE INDEX idx_expenses_company_category ON expenses(company_id, category);
CREATE INDEX idx_expenses_date ON expenses(expense_date);
CREATE INDEX idx_salary_payments_month_year ON salary_payments(company_id, payment_month, payment_year);

-- Insert default subscription plans
INSERT INTO subscription_plans (plan_name, plan_code, description, price, max_employees, max_machines, max_projects, features) VALUES 
('Basic Plan', 'basic', 'Perfect for small construction companies', 99.00, 25, 50, 25, '["employee_management", "machine_tracking", "basic_reports"]'),
('Professional Plan', 'professional', 'Ideal for growing construction businesses', 199.00, 100, 200, 100, '["employee_management", "machine_tracking", "contract_management", "parking_management", "advanced_reports"]'),
('Enterprise Plan', 'enterprise', 'Complete solution for large construction companies', 399.00, 500, 1000, 500, '["employee_management", "machine_tracking", "contract_management", "parking_management", "area_rental", "advanced_reports", "api_access", "custom_integrations"]');

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES 
('default_working_hours_per_day', '9', 'integer', 'Default working hours per day for employees'),
('default_leave_days_per_year', '20', 'integer', 'Default leave days per year for employees'),
('salary_calculation_days', '30', 'integer', 'Number of days used for salary calculation'),
('trial_period_days', '14', 'integer', 'Default trial period for new companies'),
('currency', 'USD', 'string', 'Default currency for the system'),
('timezone', 'UTC', 'string', 'Default timezone for the system');

-- Insert super admin user
INSERT INTO users (username, email, password_hash, first_name, last_name, role) VALUES 
('superadmin', 'admin@construction-saas.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super', 'Admin', 'super_admin');