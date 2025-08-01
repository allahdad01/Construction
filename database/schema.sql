-- Construction Company SaaS Platform Database Schema

-- Create database
CREATE DATABASE IF NOT EXISTS construction_saas;
USE construction_saas;

-- Employees table (Drivers and Driver Assistants)
CREATE TABLE employees (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_code VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE,
    phone VARCHAR(20),
    position ENUM('driver', 'driver_assistant') NOT NULL,
    monthly_salary DECIMAL(10,2) NOT NULL,
    daily_rate DECIMAL(10,2) GENERATED ALWAYS AS (monthly_salary / 30) STORED,
    hire_date DATE NOT NULL,
    status ENUM('active', 'inactive', 'terminated') DEFAULT 'active',
    termination_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Machines table
CREATE TABLE machines (
    id INT PRIMARY KEY AUTO_INCREMENT,
    machine_code VARCHAR(20) UNIQUE NOT NULL,
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
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Projects table
CREATE TABLE projects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    project_code VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    client_name VARCHAR(100),
    client_contact VARCHAR(100),
    start_date DATE,
    end_date DATE,
    status ENUM('planning', 'active', 'completed', 'cancelled') DEFAULT 'planning',
    total_budget DECIMAL(12,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Contracts table
CREATE TABLE contracts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    contract_code VARCHAR(20) UNIQUE NOT NULL,
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
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE SET NULL
);

-- Working hours tracking
CREATE TABLE working_hours (
    id INT PRIMARY KEY AUTO_INCREMENT,
    contract_id INT,
    machine_id INT,
    employee_id INT,
    date DATE NOT NULL,
    hours_worked DECIMAL(4,2) DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL
);

-- Parking spaces table
CREATE TABLE parking_spaces (
    id INT PRIMARY KEY AUTO_INCREMENT,
    space_code VARCHAR(20) UNIQUE NOT NULL,
    space_name VARCHAR(100) NOT NULL,
    space_type ENUM('machine', 'container', 'equipment') NOT NULL,
    size VARCHAR(50),
    monthly_rate DECIMAL(10,2) NOT NULL,
    daily_rate DECIMAL(10,2) GENERATED ALWAYS AS (monthly_rate / 30) STORED,
    status ENUM('available', 'occupied', 'maintenance') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Parking rentals
CREATE TABLE parking_rentals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    rental_code VARCHAR(20) UNIQUE NOT NULL,
    parking_space_id INT,
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
    FOREIGN KEY (parking_space_id) REFERENCES parking_spaces(id) ON DELETE CASCADE
);

-- Area rentals table
CREATE TABLE rental_areas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    area_code VARCHAR(20) UNIQUE NOT NULL,
    area_name VARCHAR(100) NOT NULL,
    area_type ENUM('storage', 'workshop', 'office', 'other') NOT NULL,
    size VARCHAR(50),
    monthly_rate DECIMAL(10,2) NOT NULL,
    daily_rate DECIMAL(10,2) GENERATED ALWAYS AS (monthly_rate / 30) STORED,
    status ENUM('available', 'occupied', 'maintenance') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Area rentals
CREATE TABLE area_rentals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    rental_code VARCHAR(20) UNIQUE NOT NULL,
    rental_area_id INT,
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
    FOREIGN KEY (rental_area_id) REFERENCES rental_areas(id) ON DELETE CASCADE
);

-- Expenses table
CREATE TABLE expenses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    expense_code VARCHAR(20) UNIQUE NOT NULL,
    category ENUM('fuel', 'maintenance', 'salary', 'rent', 'utilities', 'insurance', 'other') NOT NULL,
    description VARCHAR(200) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    expense_date DATE NOT NULL,
    payment_method ENUM('cash', 'bank_transfer', 'check', 'credit_card') DEFAULT 'cash',
    reference_number VARCHAR(50),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Salary payments table
CREATE TABLE salary_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    payment_code VARCHAR(20) UNIQUE NOT NULL,
    employee_id INT,
    payment_month INT NOT NULL,
    payment_year INT NOT NULL,
    working_days INT DEFAULT 0,
    daily_rate DECIMAL(10,2) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    amount_paid DECIMAL(10,2) DEFAULT 0,
    payment_date DATE,
    payment_method ENUM('cash', 'bank_transfer', 'check') DEFAULT 'bank_transfer',
    notes TEXT,
    status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

-- Users table for system access
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'manager', 'operator') DEFAULT 'operator',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create indexes for better performance
CREATE INDEX idx_employees_position ON employees(position);
CREATE INDEX idx_employees_status ON employees(status);
CREATE INDEX idx_machines_status ON machines(status);
CREATE INDEX idx_contracts_status ON contracts(status);
CREATE INDEX idx_working_hours_date ON working_hours(date);
CREATE INDEX idx_parking_rentals_status ON parking_rentals(status);
CREATE INDEX idx_area_rentals_status ON area_rentals(status);
CREATE INDEX idx_expenses_category ON expenses(category);
CREATE INDEX idx_expenses_date ON expenses(expense_date);
CREATE INDEX idx_salary_payments_month_year ON salary_payments(payment_month, payment_year);

-- Insert default admin user
INSERT INTO users (username, email, password_hash, role) VALUES 
('admin', 'admin@construction.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');