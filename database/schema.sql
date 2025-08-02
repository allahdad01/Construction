-- Construction SaaS Platform Database Schema (PostgreSQL)
-- Multi-tenant SaaS platform for construction companies

-- Enable UUID extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Companies Table
CREATE TABLE companies (
    id SERIAL PRIMARY KEY,
    company_name VARCHAR(100) NOT NULL,
    company_code VARCHAR(20) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    subscription_plan_id INTEGER DEFAULT 1,
    subscription_status VARCHAR(20) DEFAULT 'trial',
    trial_start_date DATE DEFAULT CURRENT_DATE,
    trial_end_date DATE DEFAULT (CURRENT_DATE + INTERVAL '30 days'),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Subscription Plans Table
CREATE TABLE subscription_plans (
    id SERIAL PRIMARY KEY,
    plan_name VARCHAR(50) NOT NULL,
    plan_code VARCHAR(20) UNIQUE NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    max_employees INTEGER DEFAULT 25,
    max_machines INTEGER DEFAULT 50,
    max_projects INTEGER DEFAULT 25,
    features TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Users Table
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    company_id INTEGER,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'employee',
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

-- Employees Table
CREATE TABLE employees (
    id SERIAL PRIMARY KEY,
    company_id INTEGER NOT NULL,
    user_id INTEGER,
    employee_code VARCHAR(20) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    position VARCHAR(50) NOT NULL,
    employee_type VARCHAR(20) NOT NULL DEFAULT 'driver',
    monthly_salary DECIMAL(10,2) NOT NULL,
    daily_rate DECIMAL(10,2) GENERATED ALWAYS AS (monthly_salary / 30) STORED,
    hire_date DATE NOT NULL,
    leave_days_used INTEGER DEFAULT 0,
    leave_days_remaining INTEGER DEFAULT 20,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE(company_id, employee_code)
);

-- Machines Table
CREATE TABLE machines (
    id SERIAL PRIMARY KEY,
    company_id INTEGER NOT NULL,
    machine_code VARCHAR(20) NOT NULL,
    machine_name VARCHAR(100) NOT NULL,
    machine_type VARCHAR(50) NOT NULL,
    model VARCHAR(100),
    year INTEGER,
    capacity VARCHAR(50),
    fuel_type VARCHAR(20),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    UNIQUE(company_id, machine_code)
);

-- Projects Table
CREATE TABLE projects (
    id SERIAL PRIMARY KEY,
    company_id INTEGER NOT NULL,
    project_code VARCHAR(20) NOT NULL,
    project_name VARCHAR(100) NOT NULL,
    client_name VARCHAR(100),
    location TEXT,
    start_date DATE,
    end_date DATE,
    status VARCHAR(20) DEFAULT 'active',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    UNIQUE(company_id, project_code)
);

-- Contracts Table
CREATE TABLE contracts (
    id SERIAL PRIMARY KEY,
    company_id INTEGER NOT NULL,
    project_id INTEGER NOT NULL,
    machine_id INTEGER NOT NULL,
    contract_code VARCHAR(20) NOT NULL,
    contract_type VARCHAR(20) NOT NULL DEFAULT 'hourly',
    rate_amount DECIMAL(10,2) NOT NULL,
    working_hours_per_day INTEGER DEFAULT 9,
    total_hours_required INTEGER,
    start_date DATE NOT NULL,
    end_date DATE,
    status VARCHAR(20) DEFAULT 'active',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE CASCADE,
    UNIQUE(company_id, contract_code)
);

-- Working Hours Table
CREATE TABLE working_hours (
    id SERIAL PRIMARY KEY,
    company_id INTEGER NOT NULL,
    contract_id INTEGER NOT NULL,
    employee_id INTEGER NOT NULL,
    date DATE NOT NULL,
    hours_worked DECIMAL(4,2) NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    UNIQUE(contract_id, employee_id, date)
);

-- Parking Spaces Table
CREATE TABLE parking_spaces (
    id SERIAL PRIMARY KEY,
    company_id INTEGER NOT NULL,
    space_code VARCHAR(20) NOT NULL,
    space_name VARCHAR(100) NOT NULL,
    location TEXT,
    capacity VARCHAR(50),
    monthly_rate DECIMAL(10,2) NOT NULL,
    daily_rate DECIMAL(10,2) GENERATED ALWAYS AS (monthly_rate / 30) STORED,
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    UNIQUE(company_id, space_code)
);

-- Parking Rentals Table
CREATE TABLE parking_rentals (
    id SERIAL PRIMARY KEY,
    company_id INTEGER NOT NULL,
    parking_space_id INTEGER NOT NULL,
    renter_name VARCHAR(100) NOT NULL,
    renter_email VARCHAR(100),
    renter_phone VARCHAR(20),
    start_date DATE NOT NULL,
    end_date DATE,
    monthly_rate DECIMAL(10,2) NOT NULL,
    daily_rate DECIMAL(10,2) GENERATED ALWAYS AS (monthly_rate / 30) STORED,
    total_amount DECIMAL(10,2),
    paid_amount DECIMAL(10,2) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'active',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (parking_space_id) REFERENCES parking_spaces(id) ON DELETE CASCADE
);

-- Rental Areas Table
CREATE TABLE rental_areas (
    id SERIAL PRIMARY KEY,
    company_id INTEGER NOT NULL,
    area_code VARCHAR(20) NOT NULL,
    area_name VARCHAR(100) NOT NULL,
    location TEXT,
    size VARCHAR(50),
    monthly_rate DECIMAL(10,2) NOT NULL,
    daily_rate DECIMAL(10,2) GENERATED ALWAYS AS (monthly_rate / 30) STORED,
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    UNIQUE(company_id, area_code)
);

-- Area Rentals Table
CREATE TABLE area_rentals (
    id SERIAL PRIMARY KEY,
    company_id INTEGER NOT NULL,
    rental_area_id INTEGER NOT NULL,
    renter_name VARCHAR(100) NOT NULL,
    renter_email VARCHAR(100),
    renter_phone VARCHAR(20),
    start_date DATE NOT NULL,
    end_date DATE,
    monthly_rate DECIMAL(10,2) NOT NULL,
    daily_rate DECIMAL(10,2) GENERATED ALWAYS AS (monthly_rate / 30) STORED,
    total_amount DECIMAL(10,2),
    paid_amount DECIMAL(10,2) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'active',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (rental_area_id) REFERENCES rental_areas(id) ON DELETE CASCADE
);

-- Expenses Table
CREATE TABLE expenses (
    id SERIAL PRIMARY KEY,
    company_id INTEGER NOT NULL,
    expense_code VARCHAR(20) NOT NULL,
    expense_date DATE NOT NULL,
    category VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(20),
    reference_number VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    UNIQUE(company_id, expense_code)
);

-- Salary Payments Table
CREATE TABLE salary_payments (
    id SERIAL PRIMARY KEY,
    company_id INTEGER NOT NULL,
    employee_id INTEGER NOT NULL,
    payment_code VARCHAR(20) NOT NULL,
    payment_date DATE NOT NULL,
    month INTEGER NOT NULL,
    year INTEGER NOT NULL,
    working_days INTEGER NOT NULL,
    leave_days INTEGER DEFAULT 0,
    daily_rate DECIMAL(10,2) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    paid_amount DECIMAL(10,2) DEFAULT 0,
    payment_method VARCHAR(20),
    reference_number VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    UNIQUE(company_id, payment_code)
);

-- User Payments Table
CREATE TABLE user_payments (
    id SERIAL PRIMARY KEY,
    company_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    payment_code VARCHAR(20) NOT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(20),
    reference_number VARCHAR(100),
    status VARCHAR(20) DEFAULT 'completed',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE(company_id, payment_code)
);

-- Company Payments Table
CREATE TABLE company_payments (
    id SERIAL PRIMARY KEY,
    company_id INTEGER NOT NULL,
    payment_code VARCHAR(20) NOT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(20),
    reference_number VARCHAR(100),
    status VARCHAR(20) DEFAULT 'completed',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    UNIQUE(company_id, payment_code)
);

-- Employee Attendance Table
CREATE TABLE employee_attendance (
    id SERIAL PRIMARY KEY,
    company_id INTEGER NOT NULL,
    employee_id INTEGER NOT NULL,
    date DATE NOT NULL,
    check_in TIME,
    check_out TIME,
    hours_worked DECIMAL(4,2),
    status VARCHAR(20) DEFAULT 'present',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    UNIQUE(employee_id, date)
);

-- System Settings Table
CREATE TABLE system_settings (
    id SERIAL PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type VARCHAR(20) DEFAULT 'string',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Contract Payments Table
CREATE TABLE contract_payments (
    id SERIAL PRIMARY KEY,
    company_id INTEGER NOT NULL,
    contract_id INTEGER NOT NULL,
    payment_code VARCHAR(20) NOT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(20) NOT NULL,
    reference_number VARCHAR(100),
    status VARCHAR(20) DEFAULT 'completed',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    UNIQUE(company_id, payment_code)
);

-- Currency Table
CREATE TABLE currencies (
    id SERIAL PRIMARY KEY,
    currency_code VARCHAR(3) UNIQUE NOT NULL,
    currency_name VARCHAR(50) NOT NULL,
    currency_symbol VARCHAR(5) NOT NULL,
    exchange_rate_to_usd DECIMAL(10,4) DEFAULT 1.0000,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Date Format Table
CREATE TABLE date_formats (
    id SERIAL PRIMARY KEY,
    format_code VARCHAR(20) UNIQUE NOT NULL,
    format_name VARCHAR(50) NOT NULL,
    format_pattern VARCHAR(20) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Company Settings Table
CREATE TABLE company_settings (
    id SERIAL PRIMARY KEY,
    company_id INTEGER NOT NULL,
    default_currency_id INTEGER NOT NULL,
    default_date_format_id INTEGER NOT NULL,
    timezone VARCHAR(50) DEFAULT 'UTC',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (default_currency_id) REFERENCES currencies(id),
    FOREIGN KEY (default_date_format_id) REFERENCES date_formats(id),
    UNIQUE(company_id)
);

-- Languages Table
CREATE TABLE languages (
    id SERIAL PRIMARY KEY,
    language_code VARCHAR(5) UNIQUE NOT NULL,
    language_name VARCHAR(50) NOT NULL,
    language_name_native VARCHAR(50) NOT NULL,
    direction VARCHAR(3) DEFAULT 'ltr',
    is_active BOOLEAN DEFAULT TRUE,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Language Translations Table
CREATE TABLE language_translations (
    id SERIAL PRIMARY KEY,
    language_id INTEGER NOT NULL,
    translation_key VARCHAR(100) NOT NULL,
    translation_value TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (language_id) REFERENCES languages(id) ON DELETE CASCADE,
    UNIQUE(language_id, translation_key)
);

-- Insert default subscription plans
INSERT INTO subscription_plans (plan_name, plan_code, price, max_employees, max_machines, max_projects, features) VALUES 
('Basic', 'basic', 99.00, 25, 50, 25, 'Employee & Machine Management'),
('Professional', 'professional', 199.00, 100, 200, 100, 'Employee & Machine Management, Contracts & Parking'),
('Enterprise', 'enterprise', 399.00, 500, 1000, 500, 'All Features + API Access');

-- Insert default currencies
INSERT INTO currencies (currency_code, currency_name, currency_symbol, exchange_rate_to_usd) VALUES 
('USD', 'US Dollar', '$', 1.0000),
('AFN', 'Afghan Afghani', '؋', 0.0115),
('EUR', 'Euro', '€', 1.0850),
('GBP', 'British Pound', '£', 1.2650),
('CAD', 'Canadian Dollar', 'C$', 0.7400),
('AUD', 'Australian Dollar', 'A$', 0.6600);

-- Insert date formats
INSERT INTO date_formats (format_code, format_name, format_pattern) VALUES 
('gregorian', 'Gregorian (YYYY-MM-DD)', 'Y-m-d'),
('shamsi', 'Shamsi (YYYY/MM/DD)', 'Y/m/d'),
('european', 'European (DD/MM/YYYY)', 'd/m/Y'),
('american', 'American (MM/DD/YYYY)', 'm/d/Y'),
('iso', 'ISO (YYYY-MM-DD)', 'Y-m-d');

-- Insert default languages
INSERT INTO languages (language_code, language_name, language_name_native, direction, is_default) VALUES 
('en', 'English', 'English', 'ltr', TRUE),
('da', 'Dari', 'دری', 'rtl', FALSE),
('ps', 'Pashto', 'پښتو', 'rtl', FALSE);

-- Insert English translations
INSERT INTO language_translations (language_id, translation_key, translation_value) VALUES 
(1, 'dashboard', 'Dashboard'),
(1, 'employees', 'Employees'),
(1, 'machines', 'Machines'),
(1, 'contracts', 'Contracts'),
(1, 'parking', 'Parking'),
(1, 'area_rentals', 'Area Rentals'),
(1, 'expenses', 'Expenses'),
(1, 'salary_payments', 'Salary Payments'),
(1, 'reports', 'Reports'),
(1, 'users', 'Users'),
(1, 'settings', 'Settings'),
(1, 'profile', 'Profile'),
(1, 'logout', 'Logout'),
(1, 'add', 'Add'),
(1, 'edit', 'Edit'),
(1, 'delete', 'Delete'),
(1, 'view', 'View'),
(1, 'save', 'Save'),
(1, 'cancel', 'Cancel'),
(1, 'search', 'Search'),
(1, 'filter', 'Filter'),
(1, 'status', 'Status'),
(1, 'active', 'Active'),
(1, 'inactive', 'Inactive'),
(1, 'pending', 'Pending'),
(1, 'completed', 'Completed'),
(1, 'currency', 'Currency'),
(1, 'date_format', 'Date Format'),
(1, 'language', 'Language'),
(1, 'timezone', 'Timezone'),
(1, 'company_settings', 'Company Settings'),
(1, 'total', 'Total'),
(1, 'amount', 'Amount'),
(1, 'date', 'Date'),
(1, 'name', 'Name'),
(1, 'email', 'Email'),
(1, 'phone', 'Phone'),
(1, 'position', 'Position'),
(1, 'salary', 'Salary'),
(1, 'rate', 'Rate'),
(1, 'hours', 'Hours'),
(1, 'payment', 'Payment'),
(1, 'notes', 'Notes'),
(1, 'actions', 'Actions'),
(1, 'back', 'Back'),
(1, 'next', 'Next'),
(1, 'previous', 'Previous'),
(1, 'first', 'First'),
(1, 'last', 'Last'),
(1, 'loading', 'Loading...'),
(1, 'no_data', 'No data found'),
(1, 'confirm_delete', 'Are you sure you want to delete this item?'),
(1, 'success', 'Success'),
(1, 'error', 'Error'),
(1, 'warning', 'Warning'),
(1, 'info', 'Information'),
(1, 'welcome', 'Welcome'),
(1, 'login', 'Login'),
(1, 'password', 'Password'),
(1, 'remember_me', 'Remember me'),
(1, 'forgot_password', 'Forgot password?'),
(1, 'register', 'Register'),
(1, 'create_account', 'Create Account'),
(1, 'already_have_account', 'Already have an account?'),
(1, 'dont_have_account', 'Don\'t have an account?'),
(1, 'timesheet', 'Timesheet'),
(1, 'work_hours', 'Work Hours'),
(1, 'daily_amount', 'Daily Amount'),
(1, 'total_earned', 'Total Earned'),
(1, 'total_paid', 'Total Paid'),
(1, 'remaining_amount', 'Remaining Amount'),
(1, 'progress', 'Progress'),
(1, 'current_month', 'Current Month'),
(1, 'contract_information', 'Contract Information'),
(1, 'project', 'Project'),
(1, 'machine', 'Machine'),
(1, 'employee', 'Employee'),
(1, 'contract_type', 'Contract Type'),
(1, 'required_hours', 'Required Hours'),
(1, 'working_hours_per_day', 'Working Hours/Day'),
(1, 'monthly_salary', 'Monthly Salary'),
(1, 'daily_rate', 'Daily Rate'),
(1, 'leave_days', 'Leave Days'),
(1, 'working_days', 'Working Days'),
(1, 'attendance', 'Attendance'),
(1, 'payments', 'Payments'),
(1, 'rentals', 'Rentals'),
(1, 'quick_actions', 'Quick Actions'),
(1, 'statistics', 'Statistics'),
(1, 'recent_activity', 'Recent Activity'),
(1, 'system_settings', 'System Settings'),
(1, 'user_management', 'User Management'),
(1, 'company_management', 'Company Management'),
(1, 'subscription_plans', 'Subscription Plans'),
(1, 'super_admin', 'Super Admin'),
(1, 'company_admin', 'Company Admin'),
(1, 'driver', 'Driver'),
(1, 'driver_assistant', 'Driver Assistant'),
(1, 'parking_user', 'Parking User'),
(1, 'area_renter', 'Area Renter'),
(1, 'container_renter', 'Container Renter');

-- Insert Dari translations
INSERT INTO language_translations (language_id, translation_key, translation_value) VALUES 
(2, 'dashboard', 'داشبورد'),
(2, 'employees', 'کارمندان'),
(2, 'machines', 'ماشین آلات'),
(2, 'contracts', 'قراردادها'),
(2, 'parking', 'پارکینگ'),
(2, 'area_rentals', 'اجاره فضا'),
(2, 'expenses', 'مصارف'),
(2, 'salary_payments', 'پرداخت حقوق'),
(2, 'reports', 'گزارشات'),
(2, 'users', 'کاربران'),
(2, 'settings', 'تنظیمات'),
(2, 'profile', 'پروفایل'),
(2, 'logout', 'خروج'),
(2, 'add', 'اضافه کردن'),
(2, 'edit', 'ویرایش'),
(2, 'delete', 'حذف'),
(2, 'view', 'مشاهده'),
(2, 'save', 'ذخیره'),
(2, 'cancel', 'لغو'),
(2, 'search', 'جستجو'),
(2, 'filter', 'فیلتر'),
(2, 'status', 'وضعیت'),
(2, 'active', 'فعال'),
(2, 'inactive', 'غیرفعال'),
(2, 'pending', 'در انتظار'),
(2, 'completed', 'تکمیل شده'),
(2, 'currency', 'واحد پول'),
(2, 'date_format', 'فرمت تاریخ'),
(2, 'language', 'زبان'),
(2, 'timezone', 'منطقه زمانی'),
(2, 'company_settings', 'تنظیمات شرکت'),
(2, 'total', 'مجموع'),
(2, 'amount', 'مبلغ'),
(2, 'date', 'تاریخ'),
(2, 'name', 'نام'),
(2, 'email', 'ایمیل'),
(2, 'phone', 'تلفن'),
(2, 'position', 'سمت'),
(2, 'salary', 'حقوق'),
(2, 'rate', 'نرخ'),
(2, 'hours', 'ساعت'),
(2, 'payment', 'پرداخت'),
(2, 'notes', 'یادداشت'),
(2, 'actions', 'عملیات'),
(2, 'back', 'بازگشت'),
(2, 'next', 'بعدی'),
(2, 'previous', 'قبلی'),
(2, 'first', 'اول'),
(2, 'last', 'آخر'),
(2, 'loading', 'در حال بارگذاری...'),
(2, 'no_data', 'داده‌ای یافت نشد'),
(2, 'confirm_delete', 'آیا مطمئن هستید که می‌خواهید این مورد را حذف کنید؟'),
(2, 'success', 'موفقیت'),
(2, 'error', 'خطا'),
(2, 'warning', 'هشدار'),
(2, 'info', 'اطلاعات'),
(2, 'welcome', 'خوش آمدید'),
(2, 'login', 'ورود'),
(2, 'password', 'رمز عبور'),
(2, 'remember_me', 'مرا به خاطر بسپار'),
(2, 'forgot_password', 'رمز عبور را فراموش کرده‌اید؟'),
(2, 'register', 'ثبت نام'),
(2, 'create_account', 'ایجاد حساب'),
(2, 'already_have_account', 'قبلاً حساب دارید؟'),
(2, 'dont_have_account', 'حساب ندارید؟'),
(2, 'timesheet', 'برگه زمان'),
(2, 'work_hours', 'ساعات کار'),
(2, 'daily_amount', 'مبلغ روزانه'),
(2, 'total_earned', 'کل درآمد'),
(2, 'total_paid', 'کل پرداخت شده'),
(2, 'remaining_amount', 'مبلغ باقی‌مانده'),
(2, 'progress', 'پیشرفت'),
(2, 'current_month', 'ماه جاری'),
(2, 'contract_information', 'اطلاعات قرارداد'),
(2, 'project', 'پروژه'),
(2, 'machine', 'ماشین'),
(2, 'employee', 'کارمند'),
(2, 'contract_type', 'نوع قرارداد'),
(2, 'required_hours', 'ساعات مورد نیاز'),
(2, 'working_hours_per_day', 'ساعات کار در روز'),
(2, 'monthly_salary', 'حقوق ماهانه'),
(2, 'daily_rate', 'نرخ روزانه'),
(2, 'leave_days', 'روزهای مرخصی'),
(2, 'working_days', 'روزهای کار'),
(2, 'attendance', 'حضور'),
(2, 'payments', 'پرداخت‌ها'),
(2, 'rentals', 'اجاره‌ها'),
(2, 'quick_actions', 'عملیات سریع'),
(2, 'statistics', 'آمار'),
(2, 'recent_activity', 'فعالیت اخیر'),
(2, 'system_settings', 'تنظیمات سیستم'),
(2, 'user_management', 'مدیریت کاربران'),
(2, 'company_management', 'مدیریت شرکت'),
(2, 'subscription_plans', 'طرح‌های اشتراک'),
(2, 'super_admin', 'مدیر کل'),
(2, 'company_admin', 'مدیر شرکت'),
(2, 'driver', 'راننده'),
(2, 'driver_assistant', 'کمک راننده'),
(2, 'parking_user', 'کاربر پارکینگ'),
(2, 'area_renter', 'اجاره‌کننده فضا'),
(2, 'container_renter', 'اجاره‌کننده کانتینر');

-- Insert Pashto translations
INSERT INTO language_translations (language_id, translation_key, translation_value) VALUES 
(3, 'dashboard', 'ډاشبورډ'),
(3, 'employees', 'کارمندان'),
(3, 'machines', 'ماشینونه'),
(3, 'contracts', 'تړونونه'),
(3, 'parking', 'پارکینګ'),
(3, 'area_rentals', 'ساحه کرایه'),
(3, 'expenses', 'مصارف'),
(3, 'salary_payments', 'د معاش تادیه'),
(3, 'reports', 'راپورونه'),
(3, 'users', 'کارنونه'),
(3, 'settings', 'تنظیمات'),
(3, 'profile', 'پروفایل'),
(3, 'logout', 'وتل'),
(3, 'add', 'اضافه کول'),
(3, 'edit', 'سمول'),
(3, 'delete', 'ړنګول'),
(3, 'view', 'کتل'),
(3, 'save', 'ساتل'),
(3, 'cancel', 'لغوه کول'),
(3, 'search', 'لټون'),
(3, 'filter', 'فلټر'),
(3, 'status', 'حالت'),
(3, 'active', 'فعال'),
(3, 'inactive', 'غیرفعال'),
(3, 'pending', 'په تمه کې'),
(3, 'completed', 'پوره شوی'),
(3, 'currency', 'د پیسو واحد'),
(3, 'date_format', 'د نیټې بڼه'),
(3, 'language', 'ژبه'),
(3, 'timezone', 'د وخت ساحه'),
(3, 'company_settings', 'د شرکت تنظیمات'),
(3, 'total', 'مجموع'),
(3, 'amount', 'مقدار'),
(3, 'date', 'نیټه'),
(3, 'name', 'نوم'),
(3, 'email', 'بریښنالیک'),
(3, 'phone', 'تلیفون'),
(3, 'position', 'موقف'),
(3, 'salary', 'معاش'),
(3, 'rate', 'نرخ'),
(3, 'hours', 'ساعتونه'),
(3, 'payment', 'تادیه'),
(3, 'notes', 'یادښتونه'),
(3, 'actions', 'کړنې'),
(3, 'back', 'شاته'),
(3, 'next', 'راتلونکی'),
(3, 'previous', 'پخوانی'),
(3, 'first', 'لومړی'),
(3, 'last', 'وروستی'),
(3, 'loading', 'د بارولو په حال کې...'),
(3, 'no_data', 'هیڅ معلومات و نه موندل شول'),
(3, 'confirm_delete', 'آیا تاسو ډاډه یاست چې غواړئ دا توکي ړنګ کړئ؟'),
(3, 'success', 'بریالیتوب'),
(3, 'error', 'تیروتنه'),
(3, 'warning', 'خبرداری'),
(3, 'info', 'معلومات'),
(3, 'welcome', 'ښه راغلاست'),
(3, 'login', 'ننوتل'),
(3, 'password', 'پټنوم'),
(3, 'remember_me', 'ما په یاد ولرئ'),
(3, 'forgot_password', 'پټنوم هیر شوی؟'),
(3, 'register', 'نوم لیکنه'),
(3, 'create_account', 'حساب جوړول'),
(3, 'already_have_account', 'دمخه حساب لرئ؟'),
(3, 'dont_have_account', 'حساب نلرئ؟'),
(3, 'timesheet', 'د وخت پاڼه'),
(3, 'work_hours', 'د کار ساعتونه'),
(3, 'daily_amount', 'ورځنی مقدار'),
(3, 'total_earned', 'ټول ترلاسه شوی'),
(3, 'total_paid', 'ټول تادیه شوی'),
(3, 'remaining_amount', 'پاتې مقدار'),
(3, 'progress', 'پرمختګ'),
(3, 'current_month', 'اوسنی میاشت'),
(3, 'contract_information', 'د تړون معلومات'),
(3, 'project', 'پروژه'),
(3, 'machine', 'ماشین'),
(3, 'employee', 'کارمند'),
(3, 'contract_type', 'د تړون ډول'),
(3, 'required_hours', 'اړین ساعتونه'),
(3, 'working_hours_per_day', 'د ورځې کار ساعتونه'),
(3, 'monthly_salary', 'میاشتنۍ معاش'),
(3, 'daily_rate', 'ورځنی نرخ'),
(3, 'leave_days', 'د رخصت ورځې'),
(3, 'working_days', 'د کار ورځې'),
(3, 'attendance', 'حضور'),
(3, 'payments', 'تادیې'),
(3, 'rentals', 'کرایې'),
(3, 'quick_actions', 'چټک کړنې'),
(3, 'statistics', 'احصایې'),
(3, 'recent_activity', 'وروستی فعالیت'),
(3, 'system_settings', 'د سیسټم تنظیمات'),
(3, 'user_management', 'د کارنونو مدیریت'),
(3, 'company_management', 'د شرکت مدیریت'),
(3, 'subscription_plans', 'د ګډون پلانونه'),
(3, 'super_admin', 'لوی مدیر'),
(3, 'company_admin', 'د شرکت مدیر'),
(3, 'driver', 'چلوونکی'),
(3, 'driver_assistant', 'د چلوونکی مرستیال'),
(3, 'parking_user', 'د پارکینګ کارن'),
(3, 'area_renter', 'د ساحې کرایه کوونکی'),
(3, 'container_renter', 'د کانتینر کرایه کوونکی');

-- Create super admin user
INSERT INTO users (email, password, first_name, last_name, role, is_active) VALUES 
('superadmin@construction.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super', 'Admin', 'super_admin', TRUE);