-- Sample Data for Construction Company Multi-Tenant SaaS Platform

USE construction_saas;

-- Insert sample companies
INSERT INTO companies (company_code, company_name, contact_person, contact_email, contact_phone, address, city, state, country, subscription_plan, subscription_status, trial_ends_at, max_employees, max_machines, max_projects) VALUES 
('COMP001', 'ABC Construction Ltd.', 'John Smith', 'john@abc-construction.com', '+1 (555) 123-4567', '123 Main St', 'New York', 'NY', 'USA', 'enterprise', 'active', NULL, 500, 1000, 500),
('COMP002', 'XYZ Builders Inc.', 'Sarah Johnson', 'sarah@xyz-builders.com', '+1 (555) 234-5678', '456 Oak Ave', 'Los Angeles', 'CA', 'USA', 'professional', 'active', NULL, 100, 200, 100),
('COMP003', 'City Construction Co.', 'Mike Wilson', 'mike@city-construction.com', '+1 (555) 345-6789', '789 Pine Rd', 'Chicago', 'IL', 'USA', 'basic', 'trial', DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY), 25, 50, 25),
('COMP004', 'Metro Builders', 'Lisa Brown', 'lisa@metro-builders.com', '+1 (555) 456-7890', '321 Elm St', 'Houston', 'TX', 'USA', 'professional', 'suspended', NULL, 100, 200, 100);

-- Insert sample users
INSERT INTO users (company_id, username, email, password_hash, first_name, last_name, phone, role, status, is_active) VALUES 
-- Company 1 (ABC Construction) - Enterprise
(1, 'admin1', 'admin@abc-construction.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John', 'Smith', '+1 (555) 123-4567', 'company_admin', 'active', TRUE),
(1, 'driver1', 'driver1@abc-construction.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Tom', 'Davis', '+1 (555) 111-1111', 'driver', 'active', TRUE),
(1, 'driver2', 'driver2@abc-construction.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jerry', 'Wilson', '+1 (555) 222-2222', 'driver', 'active', TRUE),
(1, 'assistant1', 'assistant1@abc-construction.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mary', 'Johnson', '+1 (555) 333-3333', 'driver_assistant', 'active', TRUE),
(1, 'parking1', 'parking1@abc-construction.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Bob', 'Miller', '+1 (555) 444-4444', 'parking_user', 'active', TRUE),
(1, 'area1', 'area1@abc-construction.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Alice', 'Brown', '+1 (555) 555-5555', 'area_renter', 'active', TRUE),

-- Company 2 (XYZ Builders) - Professional
(2, 'admin2', 'admin@xyz-builders.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sarah', 'Johnson', '+1 (555) 234-5678', 'company_admin', 'active', TRUE),
(2, 'driver3', 'driver3@xyz-builders.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'David', 'Clark', '+1 (555) 666-6666', 'driver', 'active', TRUE),
(2, 'assistant2', 'assistant2@xyz-builders.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Emma', 'Taylor', '+1 (555) 777-7777', 'driver_assistant', 'active', TRUE),
(2, 'parking2', 'parking2@xyz-builders.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Frank', 'Anderson', '+1 (555) 888-8888', 'parking_user', 'active', TRUE),

-- Company 3 (City Construction) - Basic (Trial)
(3, 'admin3', 'admin@city-construction.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mike', 'Wilson', '+1 (555) 345-6789', 'company_admin', 'active', TRUE),
(3, 'driver4', 'driver4@city-construction.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Grace', 'Lee', '+1 (555) 999-9999', 'driver', 'active', TRUE),

-- Company 4 (Metro Builders) - Suspended
(4, 'admin4', 'admin@metro-builders.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Lisa', 'Brown', '+1 (555) 456-7890', 'company_admin', 'suspended', FALSE);

-- Super Admin User (System-wide)
INSERT INTO users (company_id, username, email, password_hash, first_name, last_name, phone, role, status, is_active) VALUES 
(NULL, 'superadmin', 'superadmin@construction.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super', 'Admin', '+1 (555) 000-0000', 'super_admin', 'active', TRUE);

-- Insert sample employees
INSERT INTO employees (company_id, user_id, employee_code, name, email, phone, position, monthly_salary, hire_date, status, total_leave_days, used_leave_days, remaining_leave_days) VALUES 
-- ABC Construction Employees
(1, 2, 'EMP001', 'Tom Davis', 'driver1@abc-construction.com', '+1 (555) 111-1111', 'driver', 18000.00, '2023-01-15', 'active', 20, 5, 15),
(1, 3, 'EMP002', 'Jerry Wilson', 'driver2@abc-construction.com', '+1 (555) 222-2222', 'driver', 16000.00, '2023-02-20', 'active', 20, 3, 17),
(1, 4, 'EMP003', 'Mary Johnson', 'assistant1@abc-construction.com', '+1 (555) 333-3333', 'driver_assistant', 12000.00, '2023-03-10', 'active', 20, 8, 12),

-- XYZ Builders Employees
(2, 8, 'EMP004', 'David Clark', 'driver3@xyz-builders.com', '+1 (555) 666-6666', 'driver', 17000.00, '2023-01-10', 'active', 20, 2, 18),
(2, 9, 'EMP005', 'Emma Taylor', 'assistant2@xyz-builders.com', '+1 (555) 777-7777', 'driver_assistant', 11000.00, '2023-02-15', 'active', 20, 4, 16),

-- City Construction Employees
(3, 11, 'EMP006', 'Grace Lee', 'driver4@city-construction.com', '+1 (555) 999-9999', 'driver', 15000.00, '2023-04-01', 'active', 20, 1, 19);

-- Insert sample machines
INSERT INTO machines (company_id, machine_code, name, type, model, year_manufactured, capacity, fuel_type, status, purchase_date, purchase_cost) VALUES 
-- ABC Construction Machines
(1, 'MACH001', 'Excavator CAT 320', 'Excavator', 'CAT 320', 2020, '20 tons', 'diesel', 'available', '2020-03-15', 150000.00),
(1, 'MACH002', 'Bulldozer D6T', 'Bulldozer', 'CAT D6T', 2019, '15 tons', 'diesel', 'in_use', '2019-08-20', 120000.00),
(1, 'MACH003', 'Crane RT550', 'Crane', 'Liebherr RT550', 2021, '50 tons', 'diesel', 'maintenance', '2021-01-10', 300000.00),
(1, 'MACH004', 'Loader 950G', 'Loader', 'CAT 950G', 2020, '10 tons', 'diesel', 'available', '2020-06-12', 80000.00),

-- XYZ Builders Machines
(2, 'MACH005', 'Excavator JCB 3DX', 'Excavator', 'JCB 3DX', 2021, '8 tons', 'diesel', 'available', '2021-02-28', 90000.00),
(2, 'MACH006', 'Bulldozer D4K', 'Bulldozer', 'CAT D4K', 2020, '12 tons', 'diesel', 'in_use', '2020-11-15', 100000.00),

-- City Construction Machines
(3, 'MACH007', 'Mini Excavator', 'Mini Excavator', 'Kubota KX033', 2022, '3.5 tons', 'diesel', 'available', '2022-05-20', 45000.00);

-- Insert sample projects
INSERT INTO projects (company_id, project_code, name, description, client_name, client_contact, start_date, end_date, status, total_budget) VALUES 
-- ABC Construction Projects
(1, 'PROJ001', 'Downtown Office Complex', 'Construction of 20-story office building', 'Downtown Developers', 'contact@downtown-dev.com', '2023-01-01', '2024-06-30', 'active', 5000000.00),
(1, 'PROJ002', 'Highway Bridge Repair', 'Repair and reinforcement of highway bridge', 'State DOT', 'contact@state-dot.gov', '2023-03-15', '2023-12-31', 'active', 1200000.00),
(1, 'PROJ003', 'Shopping Mall Foundation', 'Foundation work for new shopping mall', 'Mall Developers Inc.', 'contact@mall-dev.com', '2023-02-01', '2023-08-31', 'completed', 800000.00),

-- XYZ Builders Projects
(2, 'PROJ004', 'Residential Complex', 'Construction of 50 residential units', 'Housing Corp', 'contact@housing-corp.com', '2023-01-15', '2024-03-31', 'active', 3000000.00),
(2, 'PROJ005', 'School Renovation', 'Renovation of elementary school', 'School District', 'contact@school-district.edu', '2023-04-01', '2023-10-31', 'active', 500000.00),

-- City Construction Projects
(3, 'PROJ006', 'Parking Garage', 'Construction of multi-level parking garage', 'City Council', 'contact@city-council.gov', '2023-05-01', '2023-12-31', 'active', 1500000.00);

-- Insert sample contracts
INSERT INTO contracts (company_id, contract_code, project_id, machine_id, contract_type, rate_amount, total_hours_required, total_days_required, working_hours_per_day, start_date, end_date, status, total_amount) VALUES 
-- ABC Construction Contracts
(1, 'CONT001', 1, 1, 'hourly', 150.00, 800, 0, 8, '2023-01-01', '2023-12-31', 'active', 120000.00),
(1, 'CONT002', 1, 2, 'daily', 1200.00, 0, 180, 9, '2023-01-01', '2023-12-31', 'active', 216000.00),
(1, 'CONT003', 2, 3, 'monthly', 15000.00, 270, 0, 9, '2023-03-15', '2023-12-31', 'active', 135000.00),

-- XYZ Builders Contracts
(2, 'CONT004', 4, 5, 'hourly', 120.00, 600, 0, 8, '2023-01-15', '2024-03-31', 'active', 72000.00),
(2, 'CONT005', 5, 6, 'daily', 1000.00, 0, 150, 9, '2023-04-01', '2023-10-31', 'active', 150000.00),

-- City Construction Contracts
(3, 'CONT006', 6, 7, 'hourly', 100.00, 400, 0, 8, '2023-05-01', '2023-12-31', 'active', 40000.00);

-- Insert sample working hours
INSERT INTO working_hours (company_id, contract_id, machine_id, employee_id, date, hours_worked, notes) VALUES 
-- ABC Construction Working Hours
(1, 1, 1, 1, '2023-06-01', 8.0, 'Regular shift'),
(1, 1, 1, 1, '2023-06-02', 7.5, 'Half day due to rain'),
(1, 1, 1, 1, '2023-06-03', 8.0, 'Regular shift'),
(1, 2, 2, 2, '2023-06-01', 9.0, 'Full day'),
(1, 2, 2, 2, '2023-06-02', 9.0, 'Full day'),
(1, 2, 2, 2, '2023-06-03', 8.5, 'Almost full day'),
(1, 3, 3, 1, '2023-06-01', 9.0, 'Monthly contract work'),
(1, 3, 3, 1, '2023-06-02', 9.0, 'Monthly contract work'),
(1, 3, 3, 1, '2023-06-03', 9.0, 'Monthly contract work'),

-- XYZ Builders Working Hours
(2, 4, 5, 4, '2023-06-01', 8.0, 'Regular shift'),
(2, 4, 5, 4, '2023-06-02', 8.0, 'Regular shift'),
(2, 5, 6, 4, '2023-06-01', 9.0, 'Daily contract'),
(2, 5, 6, 4, '2023-06-02', 9.0, 'Daily contract'),

-- City Construction Working Hours
(3, 6, 7, 6, '2023-06-01', 8.0, 'Regular shift'),
(3, 6, 7, 6, '2023-06-02', 7.0, 'Short day');

-- Insert sample parking spaces
INSERT INTO parking_spaces (company_id, space_code, space_name, space_type, size, monthly_rate, status) VALUES 
-- ABC Construction Parking
(1, 'PARK001', 'Heavy Equipment Area A', 'machine', '50x30m', 8000.00, 'available'),
(1, 'PARK002', 'Heavy Equipment Area B', 'machine', '50x30m', 8000.00, 'occupied'),
(1, 'PARK003', 'Container Storage A', 'container', '20x15m', 5000.00, 'available'),
(1, 'PARK004', 'Equipment Storage', 'equipment', '30x20m', 6000.00, 'available'),

-- XYZ Builders Parking
(2, 'PARK005', 'Equipment Yard A', 'machine', '40x25m', 7000.00, 'available'),
(2, 'PARK006', 'Container Yard', 'container', '25x20m', 4500.00, 'occupied'),

-- City Construction Parking
(3, 'PARK007', 'Small Equipment Area', 'machine', '30x20m', 5000.00, 'available');

-- Insert sample parking rentals
INSERT INTO parking_rentals (company_id, parking_space_id, user_id, rental_code, client_name, client_contact, machine_name, start_date, end_date, monthly_rate, total_days, total_amount, amount_paid, status) VALUES 
-- ABC Construction Rentals
(1, 2, 5, 'RENT001', 'Bob Miller', 'bob@external-company.com', 'Excavator CAT 330', '2023-05-01', '2023-07-31', 8000.00, 92, 24533.33, 20000.00, 'active'),
(1, 3, 6, 'RENT002', 'Alice Brown', 'alice@storage-company.com', 'Storage Container 40ft', '2023-06-01', '2023-08-31', 5000.00, 92, 15333.33, 15000.00, 'active'),

-- XYZ Builders Rentals
(2, 6, 10, 'RENT003', 'Frank Anderson', 'frank@construction-co.com', 'Bulldozer D7', '2023-05-15', '2023-08-15', 4500.00, 92, 13800.00, 13800.00, 'active');

-- Insert sample rental areas
INSERT INTO rental_areas (company_id, area_code, area_name, area_type, size, monthly_rate, status) VALUES 
-- ABC Construction Areas
(1, 'AREA001', 'Workshop A', 'workshop', '100x50m', 12000.00, 'available'),
(1, 'AREA002', 'Storage Warehouse', 'storage', '80x40m', 10000.00, 'occupied'),
(1, 'AREA003', 'Office Space', 'office', '200m²', 8000.00, 'available'),

-- XYZ Builders Areas
(2, 'AREA004', 'Equipment Workshop', 'workshop', '60x30m', 9000.00, 'available'),
(2, 'AREA005', 'Material Storage', 'storage', '50x25m', 6000.00, 'occupied');

-- Insert sample area rentals
INSERT INTO area_rentals (company_id, rental_area_id, user_id, rental_code, client_name, client_contact, purpose, start_date, end_date, monthly_rate, total_days, total_amount, amount_paid, status) VALUES 
-- ABC Construction Area Rentals
(1, 2, 6, 'ARENT001', 'Alice Brown', 'alice@storage-company.com', 'Material storage and processing', '2023-05-01', '2023-12-31', 10000.00, 245, 81666.67, 60000.00, 'active'),

-- XYZ Builders Area Rentals
(2, 5, 10, 'ARENT002', 'Frank Anderson', 'frank@construction-co.com', 'Equipment maintenance and storage', '2023-06-01', '2023-11-30', 6000.00, 183, 36600.00, 30000.00, 'active');

-- Insert sample expenses
INSERT INTO expenses (company_id, expense_code, category, description, amount, expense_date, payment_method, reference_number, notes) VALUES 
-- ABC Construction Expenses
(1, 'EXP001', 'fuel', 'Diesel fuel for machines', 2500.00, '2023-06-01', 'credit_card', 'INV-2023-001', 'Monthly fuel purchase'),
(1, 'EXP002', 'maintenance', 'Machine maintenance and repairs', 3500.00, '2023-06-05', 'bank_transfer', 'INV-2023-002', 'Regular maintenance'),
(1, 'EXP003', 'salary', 'Employee salary payments', 46000.00, '2023-06-15', 'bank_transfer', 'SAL-2023-006', 'June salary payments'),
(1, 'EXP004', 'rent', 'Office and warehouse rent', 8000.00, '2023-06-01', 'bank_transfer', 'RENT-2023-006', 'Monthly rent payment'),
(1, 'EXP005', 'utilities', 'Electricity and water bills', 1200.00, '2023-06-10', 'credit_card', 'UTIL-2023-006', 'Utility payments'),

-- XYZ Builders Expenses
(2, 'EXP006', 'fuel', 'Diesel fuel for equipment', 1800.00, '2023-06-01', 'credit_card', 'INV-2023-003', 'Monthly fuel purchase'),
(2, 'EXP007', 'maintenance', 'Equipment maintenance', 2200.00, '2023-06-08', 'bank_transfer', 'INV-2023-004', 'Regular maintenance'),
(2, 'EXP008', 'salary', 'Employee salary payments', 28000.00, '2023-06-15', 'bank_transfer', 'SAL-2023-007', 'June salary payments'),

-- City Construction Expenses
(3, 'EXP009', 'fuel', 'Diesel fuel for mini excavator', 800.00, '2023-06-01', 'credit_card', 'INV-2023-005', 'Monthly fuel purchase'),
(3, 'EXP010', 'maintenance', 'Mini excavator maintenance', 1200.00, '2023-06-12', 'bank_transfer', 'INV-2023-006', 'Regular maintenance');

-- Insert sample salary payments
INSERT INTO salary_payments (company_id, payment_code, employee_id, payment_month, payment_year, working_days, leave_days, daily_rate, total_amount, amount_paid, payment_date, payment_method, status) VALUES 
-- ABC Construction Salary Payments
(1, 'SAL001', 1, 6, 2023, 22, 2, 600.00, 13200.00, 13200.00, '2023-06-15', 'bank_transfer', 'paid'),
(1, 'SAL002', 2, 6, 2023, 20, 4, 533.33, 10666.67, 10666.67, '2023-06-15', 'bank_transfer', 'paid'),
(1, 'SAL003', 3, 6, 2023, 18, 6, 400.00, 7200.00, 7200.00, '2023-06-15', 'bank_transfer', 'paid'),

-- XYZ Builders Salary Payments
(2, 'SAL004', 4, 6, 2023, 21, 3, 566.67, 11900.00, 11900.00, '2023-06-15', 'bank_transfer', 'paid'),
(2, 'SAL005', 5, 6, 2023, 19, 5, 366.67, 6966.67, 6966.67, '2023-06-15', 'bank_transfer', 'paid'),

-- City Construction Salary Payments
(3, 'SAL006', 6, 6, 2023, 20, 4, 500.00, 10000.00, 10000.00, '2023-06-15', 'bank_transfer', 'paid');

-- Insert sample employee attendance
INSERT INTO employee_attendance (company_id, employee_id, date, status, check_in_time, check_out_time, working_hours, leave_type, notes) VALUES 
-- ABC Construction Attendance
(1, 1, '2023-06-01', 'present', '08:00:00', '17:00:00', 8.0, NULL, 'Regular shift'),
(1, 1, '2023-06-02', 'present', '08:00:00', '16:30:00', 7.5, NULL, 'Half day due to rain'),
(1, 1, '2023-06-03', 'present', '08:00:00', '17:00:00', 8.0, NULL, 'Regular shift'),
(1, 1, '2023-06-04', 'leave', NULL, NULL, 0.0, 'sick_leave', 'Sick leave'),
(1, 1, '2023-06-05', 'leave', NULL, NULL, 0.0, 'sick_leave', 'Sick leave'),

(1, 2, '2023-06-01', 'present', '08:00:00', '17:00:00', 8.0, NULL, 'Regular shift'),
(1, 2, '2023-06-02', 'present', '08:00:00', '17:00:00', 8.0, NULL, 'Regular shift'),
(1, 2, '2023-06-03', 'leave', NULL, NULL, 0.0, 'annual_leave', 'Annual leave'),
(1, 2, '2023-06-04', 'leave', NULL, NULL, 0.0, 'annual_leave', 'Annual leave'),
(1, 2, '2023-06-05', 'present', '08:00:00', '17:00:00', 8.0, NULL, 'Regular shift'),

(1, 3, '2023-06-01', 'present', '08:00:00', '17:00:00', 8.0, NULL, 'Regular shift'),
(1, 3, '2023-06-02', 'present', '08:00:00', '17:00:00', 8.0, NULL, 'Regular shift'),
(1, 3, '2023-06-03', 'present', '08:00:00', '17:00:00', 8.0, NULL, 'Regular shift'),
(1, 3, '2023-06-04', 'leave', NULL, NULL, 0.0, 'personal_leave', 'Personal leave'),
(1, 3, '2023-06-05', 'leave', NULL, NULL, 0.0, 'personal_leave', 'Personal leave'),

-- XYZ Builders Attendance
(2, 4, '2023-06-01', 'present', '08:00:00', '17:00:00', 8.0, NULL, 'Regular shift'),
(2, 4, '2023-06-02', 'present', '08:00:00', '17:00:00', 8.0, NULL, 'Regular shift'),
(2, 4, '2023-06-03', 'present', '08:00:00', '17:00:00', 8.0, NULL, 'Regular shift'),
(2, 4, '2023-06-04', 'leave', NULL, NULL, 0.0, 'annual_leave', 'Annual leave'),
(2, 4, '2023-06-05', 'present', '08:00:00', '17:00:00', 8.0, NULL, 'Regular shift'),

(2, 5, '2023-06-01', 'present', '08:00:00', '17:00:00', 8.0, NULL, 'Regular shift'),
(2, 5, '2023-06-02', 'present', '08:00:00', '17:00:00', 8.0, NULL, 'Regular shift'),
(2, 5, '2023-06-03', 'leave', NULL, NULL, 0.0, 'sick_leave', 'Sick leave'),
(2, 5, '2023-06-04', 'leave', NULL, NULL, 0.0, 'sick_leave', 'Sick leave'),
(2, 5, '2023-06-05', 'present', '08:00:00', '17:00:00', 8.0, NULL, 'Regular shift'),

-- City Construction Attendance
(3, 6, '2023-06-01', 'present', '08:00:00', '17:00:00', 8.0, NULL, 'Regular shift'),
(3, 6, '2023-06-02', 'present', '08:00:00', '16:00:00', 7.0, NULL, 'Short day'),
(3, 6, '2023-06-03', 'present', '08:00:00', '17:00:00', 8.0, NULL, 'Regular shift'),
(3, 6, '2023-06-04', 'leave', NULL, NULL, 0.0, 'annual_leave', 'Annual leave'),
(3, 6, '2023-06-05', 'present', '08:00:00', '17:00:00', 8.0, NULL, 'Regular shift');

-- Insert sample company payments
INSERT INTO company_payments (company_id, payment_code, amount, currency, payment_method, payment_status, billing_period_start, billing_period_end, subscription_plan, transaction_id, payment_date, notes) VALUES 
-- ABC Construction Payments
(1, 'PAY001', 399.00, 'USD', 'credit_card', 'completed', '2023-06-01', '2023-06-30', 'enterprise', 'TXN-2023-001', '2023-06-01', 'June subscription payment'),
(1, 'PAY002', 399.00, 'USD', 'credit_card', 'completed', '2023-05-01', '2023-05-31', 'enterprise', 'TXN-2023-002', '2023-05-01', 'May subscription payment'),
(1, 'PAY003', 399.00, 'USD', 'credit_card', 'completed', '2023-04-01', '2023-04-30', 'enterprise', 'TXN-2023-003', '2023-04-01', 'April subscription payment'),

-- XYZ Builders Payments
(2, 'PAY004', 199.00, 'USD', 'credit_card', 'completed', '2023-06-01', '2023-06-30', 'professional', 'TXN-2023-004', '2023-06-01', 'June subscription payment'),
(2, 'PAY005', 199.00, 'USD', 'credit_card', 'completed', '2023-05-01', '2023-05-31', 'professional', 'TXN-2023-005', '2023-05-01', 'May subscription payment');

-- Insert sample pricing plans
INSERT INTO pricing_plans (plan_name, plan_code, description, price, currency, billing_cycle, is_popular, is_active, max_employees, max_machines, max_projects, features) VALUES 
('Basic', 'BASIC', 'Perfect for small construction companies', 99.00, 'USD', 'monthly', FALSE, TRUE, 10, 25, 10, '["Employee Management", "Machine Tracking", "Basic Reports", "Email Support", "Mobile Access"]'),
('Professional', 'PROFESSIONAL', 'Ideal for growing construction businesses', 199.00, 'USD', 'monthly', TRUE, TRUE, 50, 100, 50, '["Everything in Basic", "Advanced Analytics", "Priority Support", "API Access", "Custom Reports", "Multi-currency Support"]'),
('Enterprise', 'ENTERPRISE', 'Complete solution for large construction companies', 399.00, 'USD', 'monthly', FALSE, TRUE, 0, 0, 0, '["Everything in Professional", "Unlimited Everything", "Dedicated Support", "Custom Integrations", "White-label Options", "Advanced Security"]');

-- City Construction Payments (Trial - no payments yet)
-- Metro Builders Payments (Suspended - no recent payments)

-- Insert sample user payments
INSERT INTO user_payments (company_id, user_id, payment_code, payment_type, rental_id, amount, payment_date, payment_method, status, notes) VALUES 
-- ABC Construction User Payments
(1, 5, 'UP001', 'parking_rental', 1, 8000.00, '2023-06-01', 'bank_transfer', 'paid', 'June parking rental payment'),
(1, 5, 'UP002', 'parking_rental', 1, 8000.00, '2023-07-01', 'bank_transfer', 'paid', 'July parking rental payment'),
(1, 5, 'UP003', 'parking_rental', 1, 4533.33, '2023-08-01', 'bank_transfer', 'pending', 'August parking rental payment'),

(1, 6, 'UP004', 'area_rental', 1, 10000.00, '2023-06-01', 'bank_transfer', 'paid', 'June area rental payment'),
(1, 6, 'UP005', 'area_rental', 1, 10000.00, '2023-07-01', 'bank_transfer', 'paid', 'July area rental payment'),
(1, 6, 'UP006', 'area_rental', 1, 10000.00, '2023-08-01', 'bank_transfer', 'pending', 'August area rental payment'),

-- XYZ Builders User Payments
(2, 10, 'UP007', 'parking_rental', 3, 4500.00, '2023-06-01', 'bank_transfer', 'paid', 'June parking rental payment'),
(2, 10, 'UP008', 'parking_rental', 3, 4500.00, '2023-07-01', 'bank_transfer', 'paid', 'July parking rental payment'),
(2, 10, 'UP009', 'parking_rental', 3, 4800.00, '2023-08-01', 'bank_transfer', 'pending', 'August parking rental payment'),

(2, 10, 'UP010', 'area_rental', 2, 6000.00, '2023-06-01', 'bank_transfer', 'paid', 'June area rental payment'),
(2, 10, 'UP011', 'area_rental', 2, 6000.00, '2023-07-01', 'bank_transfer', 'paid', 'July area rental payment'),
(2, 10, 'UP012', 'area_rental', 2, 6000.00, '2023-08-01', 'bank_transfer', 'pending', 'August area rental payment');

-- Insert sample contract payments
INSERT INTO contract_payments (company_id, contract_id, payment_code, payment_date, amount, payment_method, reference_number, status, notes) VALUES 
-- ABC Construction Contract Payments
(1, 1, 'PAY000001', '2023-06-15', 12000.00, 'bank_transfer', 'TXN-2023-001', 'completed', 'June payment for hourly contract'),
(1, 1, 'PAY000002', '2023-07-15', 15000.00, 'credit_card', 'TXN-2023-002', 'completed', 'July payment for hourly contract'),
(1, 2, 'PAY000003', '2023-06-20', 20000.00, 'bank_transfer', 'TXN-2023-003', 'completed', 'June payment for daily contract'),
(1, 2, 'PAY000004', '2023-07-20', 18000.00, 'bank_transfer', 'TXN-2023-004', 'completed', 'July payment for daily contract'),
(1, 3, 'PAY000005', '2023-06-30', 15000.00, 'credit_card', 'TXN-2023-005', 'completed', 'June payment for monthly contract'),

-- XYZ Builders Contract Payments
(2, 4, 'PAY000006', '2023-06-15', 8000.00, 'bank_transfer', 'TXN-2023-006', 'completed', 'June payment for hourly contract'),
(2, 4, 'PAY000007', '2023-07-15', 10000.00, 'credit_card', 'TXN-2023-007', 'completed', 'July payment for hourly contract'),
(2, 5, 'PAY000008', '2023-06-25', 15000.00, 'bank_transfer', 'TXN-2023-008', 'completed', 'June payment for daily contract'),

-- City Construction Contract Payments
(3, 6, 'PAY000009', '2023-06-15', 5000.00, 'bank_transfer', 'TXN-2023-009', 'completed', 'June payment for hourly contract'),
(3, 6, 'PAY000010', '2023-07-15', 6000.00, 'credit_card', 'TXN-2023-010', 'pending', 'July payment for hourly contract');

-- Update company employee counts
UPDATE companies SET employee_count = (SELECT COUNT(*) FROM employees WHERE company_id = companies.id);

-- Update company subscription status for trial companies
UPDATE companies SET subscription_status = 'trial' WHERE id = 3;
UPDATE companies SET subscription_status = 'suspended' WHERE id = 4;

-- Insert company settings with different currencies and date formats
INSERT INTO company_settings (company_id, setting_key, setting_value) VALUES 
(1, 'default_currency_id', '1'), -- ABC Construction: USD
(1, 'default_date_format_id', '1'), -- ABC Construction: Gregorian
(2, 'default_currency_id', '2'), -- XYZ Builders: AFN
(2, 'default_date_format_id', '2'), -- XYZ Builders: Shamsi
(3, 'default_currency_id', '1'), -- City Construction: USD
(3, 'default_date_format_id', '3'), -- City Construction: European
(4, 'default_currency_id', '3'), -- Metro Builders: EUR
(4, 'default_date_format_id', '1'); -- Metro Builders: Gregorian

-- Currency and date format settings are stored in company_settings table
-- No need to update individual table currency columns as they don't exist in the schema