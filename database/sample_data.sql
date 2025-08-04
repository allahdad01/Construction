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
INSERT INTO contracts (company_id, contract_code, project_id, machine_id, contract_type, rate_amount, currency, total_hours_required, total_days_required, working_hours_per_day, start_date, end_date, status, total_amount) VALUES 
-- ABC Construction Contracts
(1, 'CONT001', 1, 1, 'hourly', 150.00, 'USD', 800, 0, 8, '2023-01-01', '2023-12-31', 'active', 120000.00),
(1, 'CONT002', 1, 2, 'daily', 1200.00, 'USD', 0, 180, 9, '2023-01-01', '2023-12-31', 'active', 216000.00),
(1, 'CONT003', 2, 3, 'monthly', 15000.00, 'USD', 270, 0, 9, '2023-03-15', '2023-12-31', 'active', 135000.00),

-- XYZ Builders Contracts
(2, 'CONT004', 4, 5, 'hourly', 120.00, 'USD', 600, 0, 8, '2023-01-15', '2024-03-31', 'active', 72000.00),
(2, 'CONT005', 5, 6, 'daily', 1000.00, 'USD', 0, 150, 9, '2023-04-01', '2023-10-31', 'active', 150000.00),

-- City Construction Contracts
(3, 'CONT006', 6, 7, 'hourly', 100.00, 'USD', 400, 0, 8, '2023-05-01', '2023-12-31', 'active', 40000.00);

-- Insert sample expenses with different currencies
INSERT INTO expenses (company_id, expense_code, category, description, amount, currency, expense_date, payment_method, reference_number, notes) VALUES 
-- ABC Construction Expenses (USD)
(1, 'EXP001', 'Fuel', 'Diesel fuel for excavators', 2500.00, 'USD', '2023-06-15', 'bank_transfer', 'REF001', 'Monthly fuel expense'),
(1, 'EXP002', 'Maintenance', 'Machine maintenance and repairs', 1800.00, 'USD', '2023-06-20', 'credit_card', 'REF002', 'Regular maintenance'),
(1, 'EXP003', 'Supplies', 'Construction materials and supplies', 3200.00, 'USD', '2023-06-25', 'bank_transfer', 'REF003', 'Project supplies'),

-- XYZ Builders Expenses (Mixed currencies)
(2, 'EXP004', 'Fuel', 'Diesel fuel for machinery', 1875.00, 'AFN', '2023-06-10', 'cash', 'REF004', 'Local fuel purchase'),
(2, 'EXP005', 'Equipment', 'New safety equipment', 1200.00, 'USD', '2023-06-18', 'credit_card', 'REF005', 'Safety gear purchase'),
(2, 'EXP006', 'Transportation', 'Material transportation costs', 900.00, 'AFN', '2023-06-22', 'cash', 'REF006', 'Local transport'),

-- City Construction Expenses (AFN)
(3, 'EXP007', 'Fuel', 'Fuel for mini excavator', 675.00, 'AFN', '2023-06-12', 'cash', 'REF007', 'Small project fuel'),
(3, 'EXP008', 'Supplies', 'Basic construction supplies', 450.00, 'AFN', '2023-06-28', 'cash', 'REF008', 'Local supplies');

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

-- XYZ Builders Payments (Mixed currencies)
(2, 'PAY004', 199.00, 'USD', 'credit_card', 'completed', '2023-06-01', '2023-06-30', 'professional', 'TXN-2023-004', '2023-06-01', 'June subscription payment'),
(2, 'PAY005', 14925.00, 'AFN', 'bank_transfer', 'completed', '2023-05-01', '2023-05-31', 'professional', 'TXN-2023-005', '2023-05-01', 'May subscription payment (local currency)'),
(2, 'PAY006', 199.00, 'USD', 'credit_card', 'pending', '2023-07-01', '2023-07-31', 'professional', 'TXN-2023-006', '2023-07-01', 'July subscription payment (pending)');

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

-- Insert comprehensive language translations
INSERT INTO language_translations (language_id, translation_key, translation_value) VALUES
-- English translations (language_id = 1)
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
(1, 'login', 'Login'),
(1, 'register', 'Register'),
(1, 'email', 'Email'),
(1, 'password', 'Password'),
(1, 'remember_me', 'Remember Me'),
(1, 'forgot_password', 'Forgot Password?'),
(1, 'submit', 'Submit'),
(1, 'cancel', 'Cancel'),
(1, 'save', 'Save'),
(1, 'edit', 'Edit'),
(1, 'delete', 'Delete'),
(1, 'view', 'View'),
(1, 'add', 'Add'),
(1, 'search', 'Search'),
(1, 'filter', 'Filter'),
(1, 'status', 'Status'),
(1, 'active', 'Active'),
(1, 'inactive', 'Inactive'),
(1, 'pending', 'Pending'),
(1, 'completed', 'Completed'),
(1, 'success', 'Success'),
(1, 'error', 'Error'),
(1, 'warning', 'Warning'),
(1, 'info', 'Information'),
(1, 'confirm_delete', 'Are you sure you want to delete this item?'),
(1, 'no_data', 'No data found'),
(1, 'loading', 'Loading...'),
(1, 'back', 'Back'),
(1, 'next', 'Next'),
(1, 'previous', 'Previous'),
(1, 'first', 'First'),
(1, 'last', 'Last'),
(1, 'total', 'Total'),
(1, 'amount', 'Amount'),
(1, 'date', 'Date'),
(1, 'name', 'Name'),
(1, 'phone', 'Phone'),
(1, 'position', 'Position'),
(1, 'salary', 'Salary'),
(1, 'rate', 'Rate'),
(1, 'hours', 'Hours'),
(1, 'payment', 'Payment'),
(1, 'notes', 'Notes'),
(1, 'actions', 'Actions'),
(1, 'currency', 'Currency'),
(1, 'date_format', 'Date Format'),
(1, 'language', 'Language'),
(1, 'timezone', 'Timezone'),
(1, 'company_settings', 'Company Settings'),
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
(1, 'working_hours_per_day', 'Working Hours per Day'),
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
(1, 'container_renter', 'Container Renter'),
(1, 'pricing_plans', 'Pricing Plans'),
(1, 'add_pricing_plan', 'Add Pricing Plan'),
(1, 'edit_pricing_plan', 'Edit Pricing Plan'),
(1, 'plan_name', 'Plan Name'),
(1, 'plan_code', 'Plan Code'),
(1, 'price', 'Price'),
(1, 'billing_cycle', 'Billing Cycle'),
(1, 'features', 'Features'),
(1, 'is_popular', 'Popular Plan'),
(1, 'is_active', 'Active Plan'),
(1, 'max_employees', 'Max Employees'),
(1, 'max_machines', 'Max Machines'),
(1, 'max_projects', 'Max Projects'),
(1, 'monthly', 'Monthly'),
(1, 'quarterly', 'Quarterly'),
(1, 'yearly', 'Yearly'),
(1, 'unlimited', 'Unlimited'),
(1, 'basic', 'Basic'),
(1, 'professional', 'Professional'),
(1, 'enterprise', 'Enterprise'),
(1, 'employee_management', 'Employee Management'),
(1, 'machine_tracking', 'Machine Tracking'),
(1, 'basic_reports', 'Basic Reports'),
(1, 'email_support', 'Email Support'),
(1, 'mobile_access', 'Mobile Access'),
(1, 'advanced_analytics', 'Advanced Analytics'),
(1, 'priority_support', 'Priority Support'),
(1, 'api_access', 'API Access'),
(1, 'custom_reports', 'Custom Reports'),
(1, 'multi_currency_support', 'Multi-currency Support'),
(1, 'unlimited_everything', 'Unlimited Everything'),
(1, 'dedicated_support', 'Dedicated Support'),
(1, 'custom_integrations', 'Custom Integrations'),
(1, 'white_label_options', 'White-label Options'),
(1, 'advanced_security', 'Advanced Security'),
(1, 'most_popular', 'Most Popular'),
(1, 'get_started', 'Get Started'),
(1, 'choose_your_plan', 'Choose Your Plan'),
(1, 'flexible_pricing_plans', 'Flexible pricing plans designed for construction companies of all sizes'),
(1, 'perfect_for_small_companies', 'Perfect for small construction companies'),
(1, 'ideal_for_growing_businesses', 'Ideal for growing construction businesses'),
(1, 'complete_solution_large_companies', 'Complete solution for large construction companies'),
(1, 'up_to_10_employees', 'Up to 10 employees'),
(1, 'up_to_50_employees', 'Up to 50 employees'),
(1, 'unlimited_employees', 'Unlimited employees'),
(1, 'up_to_25_machines', 'Up to 25 machines'),
(1, 'up_to_100_machines', 'Up to 100 machines'),
(1, 'unlimited_machines', 'Unlimited machines'),
(1, 'everything_in_basic', 'Everything in Basic'),
(1, 'everything_in_professional', 'Everything in Professional'),
(1, 'language_changed_successfully', 'Language changed successfully'),
(1, 'failed_to_change_language', 'Failed to change language'),
(1, 'invalid_language', 'Invalid language'),
(1, 'language_parameter_required', 'Language parameter is required'),
(1, 'pricing_plan_added_successfully', 'Pricing plan added successfully!'),
(1, 'pricing_plan_updated_successfully', 'Pricing plan updated successfully!'),
(1, 'pricing_plan_deleted_successfully', 'Pricing plan deleted successfully!'),
(1, 'cannot_delete_plan_in_use', 'Cannot delete this plan because {count} companies are currently using it.'),
(1, 'plan_code_already_exists', 'Plan code already exists. Please choose a different one.'),
(1, 'price_must_be_positive', 'Price must be a positive number.'),
(1, 'field_required', 'Field "{field}" is required.'),
(1, 'please_fill_required_fields', 'Please fill in all required fields.'),
(1, 'price_must_be_greater_than_zero', 'Price must be greater than zero.'),
(1, 'plan_code_format_error', 'Plan code should only contain uppercase letters, numbers, and underscores.'),
(1, 'companies_using_plan', 'Companies Using'),
(1, 'features_count', 'Features Count'),
(1, 'plan_type', 'Plan Type'),
(1, 'current_plan_info', 'Current Plan Info'),
(1, 'plan_types', 'Plan Types'),
(1, 'billing_cycles', 'Billing Cycles'),
(1, 'popular_features', 'Popular Features'),
(1, 'tips', 'Tips'),
(1, 'use_clear_descriptive_names', 'Use clear, descriptive plan names'),
(1, 'set_reasonable_limits', 'Set reasonable limits for each tier'),
(1, 'highlight_key_features', 'Highlight key features in descriptions'),
(1, 'mark_best_value_popular', 'Mark your best value plan as popular'),
(1, 'for_small_companies', 'For small companies'),
(1, 'for_growing_businesses', 'For growing businesses'),
(1, 'for_large_companies', 'For large companies'),
(1, 'billed_every_month', 'Billed every month'),
(1, 'billed_every_3_months', 'Billed every 3 months'),
(1, 'billed_annually', 'Billed annually'),
(1, 'reports_analytics', 'Reports & Analytics'),
(1, 'customer_support', 'Customer Support'),
(1, 'api_access', 'API Access'),
(1, 'total_plans', 'Total Plans'),
(1, 'active_plans', 'Active Plans'),
(1, 'popular_plans', 'Popular Plans'),
(1, 'average_price', 'Average Price'),
(1, 'all_status', 'All Status'),
(1, 'no_pricing_plans_found', 'No pricing plans found'),
(1, 'add_first_pricing_plan', 'Add your first pricing plan to get started.'),
(1, 'pricing_plans_management', 'Pricing Plans Management'),
(1, 'search_by_plan_name', 'Search by plan name, code, or description'),
(1, 'select_cycle', 'Select Cycle'),
(1, 'unique_identifier', 'Unique identifier for the plan'),
(1, 'brief_description', 'Brief description of the plan'),
(1, 'plan_limits', 'Plan Limits'),
(1, 'plan_features', 'Plan Features'),
(1, 'plan_settings', 'Plan Settings'),
(1, 'enter_features_one_per_line', 'Enter features, one per line'),
(1, 'popular_plans_highlighted', 'Popular plans are highlighted on the landing page'),
(1, 'inactive_plans_not_shown', 'Inactive plans won\'t be shown to customers'),
(1, 'update_pricing_plan', 'Update Pricing Plan'),
(1, 'back_to_pricing_plans', 'Back to Pricing Plans'),
(1, 'back_to_plan', 'Back to Plan'),
(1, 'plan_details', 'Plan Details'),
(1, 'plan_summary', 'Plan Summary'),
(1, 'quick_actions', 'Quick Actions'),
(1, 'plan_statistics', 'Plan Statistics'),
(1, 'information', 'Information'),
(1, 'current_plan_info', 'Current Plan Info'),
(1, 'popular_plan', 'Popular Plan'),
(1, 'manage_companies', 'Manage Companies'),
(1, 'add_company', 'Add Company'),
(1, 'search_filter', 'Search & Filter'),
(1, 'search_by_company_name_email_code', 'Search by company name, email, or code'),
(1, 'all_status', 'All Status'),
(1, 'active', 'Active'),
(1, 'trial', 'Trial'),
(1, 'suspended', 'Suspended'),
(1, 'cancelled', 'Cancelled'),
(1, 'all_plans', 'All Plans'),
(1, 'basic', 'Basic'),
(1, 'professional', 'Professional'),
(1, 'enterprise', 'Enterprise'),
(1, 'search', 'Search'),
(1, 'clear', 'Clear'),
(1, 'company_list', 'Company List'),
(1, 'no_companies_found', 'No companies found.'),
(1, 'add_first_company', 'Add First Company'),
(1, 'company_code', 'Company Code'),
(1, 'company_name', 'Company Name'),
(1, 'contact', 'Contact'),
(1, 'subscription', 'Subscription'),
(1, 'usage', 'Usage'),
(1, 'status', 'Status'),
(1, 'created', 'Created'),
(1, 'actions', 'Actions'),
(1, 'total_companies', 'Total Companies'),
(1, 'active_companies', 'Active Companies'),
(1, 'trial_companies', 'Trial Companies'),
(1, 'total_revenue', 'Total Revenue'),
(1, 'super_admin_dashboard', 'Super Admin Dashboard'),
(1, 'active_subscriptions', 'Active Subscriptions'),
(1, 'monthly_revenue', 'Monthly Revenue'),
(1, 'manage_plans', 'Manage Plans'),
(1, 'view_payments', 'View Payments'),
(1, 'recent_companies', 'Recent Companies'),
(1, 'view_all_companies', 'View All Companies'),
(1, 'recent_payments', 'Recent Payments'),
(1, 'no_payments_found', 'No payments found.'),
(1, 'view_all_payments', 'View All Payments'),
(1, 'subscription_plan_statistics', 'Subscription Plan Statistics'),
(1, 'no_subscription_data_available', 'No subscription data available.'),
(1, 'plan', 'Plan'),
(1, 'companies', 'Companies'),
(1, 'system_overview', 'System Overview'),
(1, 'system_information', 'System Information'),
(1, 'total_users', 'Total Users'),
(1, 'quick_links', 'Quick Links'),
(1, 'subscription_plans', 'Subscription Plans'),
(1, 'payment_history', 'Payment History'),
(1, 'platform_expenses', 'Platform Expenses'),
(1, 'add_expense', 'Add Expense'),
(1, 'total_expenses', 'Total Expenses'),
(1, 'total_usd', 'Total USD'),
(1, 'total_afn', 'Total AFN'),
(1, 'monthly_usd', 'Monthly USD'),
(1, 'monthly_afn', 'Monthly AFN'),
(1, 'monthly_count', 'Monthly Count'),
(1, 'total_expenses_by_currency', 'Total Expenses by Currency'),
(1, 'expenses', 'Expenses'),
(1, 'no_expenses_found', 'No expenses found'),
(1, 'this_month_by_currency', 'This Month by Currency'),
(1, 'monthly_total', 'Monthly Total'),
(1, 'no_expenses_this_month', 'No expenses this month'),
(1, 'search_by_code_description_notes', 'Search by code, description, or notes'),
(1, 'all_types', 'All Types'),
(1, 'office_supplies', 'Office Supplies'),
(1, 'utilities', 'Utilities'),
(1, 'rent', 'Rent'),
(1, 'maintenance', 'Maintenance'),
(1, 'marketing', 'Marketing'),
(1, 'software', 'Software'),
(1, 'travel', 'Travel'),
(1, 'other', 'Other'),
(1, 'from_date', 'From Date'),
(1, 'to_date', 'To Date'),
(1, 'expense_code', 'Expense Code'),
(1, 'type', 'Type'),
(1, 'description', 'Description'),
(1, 'amount', 'Amount'),
(1, 'currency', 'Currency'),
(1, 'date', 'Date'),
(1, 'payment_method', 'Payment Method'),
(1, 'receipt', 'Receipt'),
(1, 'na', 'N/A'),
(1, 'confirm_delete_expense', 'Are you sure you want to delete this expense?'),
(1, 'previous', 'Previous'),
(1, 'next', 'Next'),
(1, 'add_first_expense_to_get_started', 'Add your first expense to get started.'),
(1, 'platform_payments', 'Platform Payments'),
(1, 'add_payment', 'Add Payment'),
(1, 'export', 'Export'),
(1, 'total_payments', 'Total Payments'),
(1, 'usd_received', 'USD Received'),
(1, 'afn_received', 'AFN Received'),
(1, 'pending_usd', 'Pending USD'),
(1, 'pending_afn', 'Pending AFN'),
(1, 'this_month', 'This Month'),
(1, 'search_by_payment_code_transaction_id_notes', 'Search by payment code, transaction ID, or notes'),
(1, 'all_methods', 'All Methods'),
(1, 'credit_card', 'Credit Card'),
(1, 'bank_transfer', 'Bank Transfer'),
(1, 'cash', 'Cash'),
(1, 'check', 'Check'),
(1, 'paypal', 'PayPal'),
(1, 'all_companies', 'All Companies'),
(1, 'payments', 'Payments'),
(1, 'payments_from_companies_will_appear_here', 'Payments from companies will appear here.'),
(1, 'payment_code', 'Payment Code'),
(1, 'company', 'Company'),
(1, 'method', 'Method'),
(1, 'confirm_approve_payment', 'Are you sure you want to approve this payment?'),
(1, 'failed', 'Failed'),
(1, 'please_enter_both_email_and_password', 'Please enter both email and password.'),
(1, 'invalid_email_or_password', 'Invalid email or password.'),
(1, 'multi_tenant_construction_management', 'Multi-Tenant Construction Management'),
(1, 'email_address', 'Email Address'),
(1, 'demo_accounts', 'Demo Accounts'),
(1, 'company_admin', 'Company Admin'),
(1, 'welcome', 'Welcome'),
(1, 'add_employee', 'Add Employee'),
(1, 'add_machine', 'Add Machine'),
(1, 'add_contract', 'Add Contract'),
(1, 'active_employees', 'Active Employees'),
(1, 'available_machines', 'Available Machines'),
(1, 'active_contracts', 'Active Contracts'),
(1, 'contract_value', 'Contract Value'),
(1, 'monthly_salary', 'Monthly Salary'),
(1, 'working_days', 'Working Days'),
(1, 'leave_days', 'Leave Days'),
(1, 'daily_rate', 'Daily Rate'),
(1, 'active_rentals', 'Active Rentals'),
(1, 'total_paid', 'Total Paid'),
(1, 'remaining_amount', 'Remaining Amount'),
(1, 'next_payment', 'Next Payment'),
(1, 'manage_employees', 'Manage Employees'),
(1, 'manage_machines', 'Manage Machines'),
(1, 'manage_contracts', 'Manage Contracts'),
(1, 'manage_expenses', 'Manage Expenses'),
(1, 'view_attendance', 'View Attendance'),
(1, 'view_salary', 'View Salary'),
(1, 'update_profile', 'Update Profile'),
(1, 'view_rentals', 'View Rentals'),
(1, 'make_payment', 'Make Payment'),
(1, 'welcome_to_app', 'Welcome to {app_name}'),
(1, 'welcome_message', 'Welcome to the Construction Company Management System. Please contact your administrator for access to specific features.'),
(1, 'employee_deleted_successfully', 'Employee deleted successfully!'),
(1, 'employee_not_found_or_access_denied', 'Employee not found or access denied.'),
(1, 'error_deleting_employee', 'Error deleting employee'),
(1, 'employees', 'Employees'),
(1, 'total_employees', 'Total Employees'),
(1, 'drivers', 'Drivers'),
(1, 'assistants', 'Assistants'),
(1, 'filters', 'Filters'),
(1, 'search_by_name_code_email', 'Search by name, code, or email'),
(1, 'position', 'Position'),
(1, 'all_positions', 'All Positions'),
(1, 'driver', 'Driver'),
(1, 'driver_assistant', 'Driver Assistant'),
(1, 'machine_operator', 'Machine Operator'),
(1, 'supervisor', 'Supervisor'),
(1, 'technician', 'Technician'),
(1, 'inactive', 'Inactive'),
(1, 'employees_list', 'Employees List'),
(1, 'export_options', 'Export Options'),
(1, 'export_to_csv', 'Export to CSV'),
(1, 'export_to_pdf', 'Export to PDF'),
(1, 'no_employees_found', 'No employees found'),
(1, 'add_first_employee_to_get_started', 'Add your first employee to get started.'),
(1, 'employee_code', 'Employee Code'),
(1, 'name', 'Name'),
(1, 'email', 'Email'),
(1, 'phone', 'Phone'),
(1, 'no_email', 'No email'),
(1, 'no_phone', 'No phone'),
(1, 'confirm_delete_employee', 'Are you sure you want to delete employee'),
(1, 'this_action_cannot_be_undone', 'This action cannot be undone.'),
(1, 'pdf_export_feature_coming_soon', 'PDF export feature coming soon!'),
(1, 'machine_management', 'Machine Management'),
(1, 'total_machines', 'Total Machines'),
(1, 'available', 'Available'),
(1, 'in_use', 'In Use'),
(1, 'total_value', 'Total Value'),
(1, 'search_by_name_code_model', 'Search by name, code, or model'),
(1, 'all_types', 'All Types'),
(1, 'maintenance', 'Maintenance'),
(1, 'retired', 'Retired'),
(1, 'machine_inventory', 'Machine Inventory'),
(1, 'no_machines_found', 'No machines found.'),
(1, 'add_first_machine', 'Add First Machine'),
(1, 'machine_code', 'Machine Code'),
(1, 'name_model', 'Name & Model'),
(1, 'specifications', 'Specifications'),
(1, 'value', 'Value'),
(1, 'capacity', 'Capacity'),
(1, 'fuel', 'Fuel'),
(1, 'purchase', 'Purchase'),
(1, 'confirm_retire_machine', 'Are you sure you want to retire this machine?'),
(1, 'confirm_reactivate_machine', 'Are you sure you want to reactivate this machine?'),
(1, 'add_new_machine', 'Add New Machine'),
(1, 'manage_contracts', 'Manage Contracts'),
(1, 'maintenance_schedule', 'Maintenance Schedule'),
(1, 'machine_reports', 'Machine Reports'),
(1, 'machine_statistics', 'Machine Statistics'),
(1, 'status_breakdown', 'Status Breakdown'),
(1, 'value_overview', 'Value Overview'),
(1, 'average_value', 'Average Value'),
(1, 'utilization_rate', 'Utilization Rate'),
(1, 'machines_currently_in_use', 'Machines currently in use'),
(1, 'contract_management', 'Contract Management'),
(1, 'total_contracts', 'Total Contracts'),
(1, 'active_contracts', 'Active Contracts'),
(1, 'completed', 'Completed'),
(1, 'monthly_contract_revenue', 'Monthly Contract Revenue'),
(1, 'contract_types', 'Contract Types'),
(1, 'search_by_code_project_machine', 'Search by code, project, or machine'),
(1, 'hourly', 'Hourly'),
(1, 'daily', 'Daily'),
(1, 'monthly', 'Monthly'),
(1, 'cancelled', 'Cancelled'),
(1, 'contract_list', 'Contract List'),
(1, 'no_contracts_found', 'No contracts found.'),
(1, 'add_first_contract', 'Add First Contract'),
(1, 'contract_code', 'Contract Code'),
(1, 'project_machine', 'Project & Machine'),
(1, 'type_rate', 'Type & Rate'),
(1, 'progress', 'Progress'),
(1, 'hr', 'hr'),
(1, 'day', 'day'),
(1, 'month', 'month'),
(1, 'hours', 'hours'),
(1, 'paid', 'Paid'),
(1, 'confirm_complete_contract', 'Are you sure you want to complete this contract?'),
(1, 'expense_management', 'Expense Management'),
(1, 'add_expense', 'Add Expense'),
(1, 'monthly_expenses', 'Monthly Expenses'),
(1, 'top_category', 'Top Category'),
(1, 'recent_7_days', 'Recent (7 days)'),
(1, 'search_by_description_code_reference', 'Search by description, code, or reference'),
(1, 'all_categories', 'All Categories'),
(1, 'from_date', 'From Date'),
(1, 'to_date', 'To Date'),
(1, 'expense_list', 'Expense List'),
(1, 'no_expenses_found', 'No expenses found.'),
(1, 'add_first_expense', 'Add First Expense'),
(1, 'expense_code', 'Expense Code'),
(1, 'description', 'Description'),
(1, 'amount', 'Amount'),
(1, 'date', 'Date'),
(1, 'payment_method', 'Payment Method'),
(1, 'reference', 'Reference'),
(1, 'confirm_delete_expense', 'Are you sure you want to delete this expense?'),
(1, 'category_breakdown', 'Category Breakdown'),
(1, 'count', 'Count'),
(1, 'total_amount', 'Total Amount'),
(1, 'percentage', 'Percentage'),
(1, 'company_name_required', 'Company name is required.'),
(1, 'invalid_email_format', 'Invalid email format.'),
(1, 'company_information_updated_successfully', 'Company information updated successfully!'),
(1, 'company_preferences_updated_successfully', 'Company preferences updated successfully!'),
(1, 'notification_settings_updated_successfully', 'Notification settings updated successfully!'),
(1, 'security_settings_updated_successfully', 'Security settings updated successfully!'),
(1, 'integration_settings_updated_successfully', 'Integration settings updated successfully!'),
(1, 'company_settings', 'Company Settings'),
(1, 'company_info', 'Company Info'),
(1, 'preferences', 'Preferences'),
(1, 'notifications', 'Notifications'),
(1, 'security', 'Security'),
(1, 'integrations', 'Integrations'),
(1, 'company_name', 'Company Name'),
(1, 'company_email', 'Company Email'),
(1, 'company_phone', 'Company Phone'),
(1, 'company_website', 'Company Website'),
(1, 'company_address', 'Company Address'),
(1, 'company_description', 'Company Description'),
(1, 'update_company_info', 'Update Company Info'),
(1, 'field_required', 'Field {field} is required.'),
(1, 'email_already_exists', 'Email already exists in the system.'),
(1, 'profile_updated_successfully', 'Profile updated successfully!'),
(1, 'current_password_incorrect', 'Current password is incorrect.'),
(1, 'password_min_length', 'New password must be at least 6 characters long.'),
(1, 'passwords_do_not_match', 'New passwords do not match.'),
(1, 'password_changed_successfully', 'Password changed successfully!'),
(1, 'my_profile', 'My Profile'),
(1, 'back_to_dashboard', 'Back to Dashboard'),
(1, 'profile_information', 'Profile Information'),
(1, 'first_name', 'First Name'),
(1, 'last_name', 'Last Name'),
(1, 'phone_number', 'Phone Number'),
(1, 'change_password', 'Change Password'),
(1, 'current_password', 'Current Password'),
(1, 'new_password', 'New Password'),
(1, 'confirm_password', 'Confirm Password'),
(1, 'profile_summary', 'Profile Summary'),
(1, 'member_since', 'Member Since'),
(1, 'company_information', 'Company Information'),
(1, 'company', 'Company'),
(1, 'plan', 'Plan'),
(1, 'no_plan', 'No Plan'),
(1, 'trial_ends', 'Trial Ends'),
(1, 'recent_activity', 'Recent Activity'),
(1, 'no_recent_activity', 'No recent activity'),
(1, 'cannot_delete_own_account', 'You cannot delete your own account.'),
(1, 'cannot_delete_user_with_employee_record', 'Cannot delete user. They have an associated employee record.'),
(1, 'user_deleted_successfully', 'User deleted successfully!'),
(1, 'user_management', 'User Management'),
(1, 'add_user', 'Add User'),
(1, 'total_users', 'Total Users'),
(1, 'active_users', 'Active Users'),
(1, 'admins', 'Admins'),
(1, 'drivers', 'Drivers'),
(1, 'filters', 'Filters'),
(1, 'search_by_name_or_email', 'Search by name or email'),
(1, 'all_roles', 'All Roles'),
(1, 'company_admin', 'Company Admin'),
(1, 'driver', 'Driver'),
(1, 'driver_assistant', 'Driver Assistant'),
(1, 'parking_user', 'Parking User'),
(1, 'area_renter', 'Area Renter'),
(1, 'container_renter', 'Container Renter'),
(1, 'all_status', 'All Status'),
(1, 'users_list', 'Users List'),
(1, 'export_options', 'Export Options'),
(1, 'export_to_csv', 'Export to CSV'),
(1, 'export_to_pdf', 'Export to PDF'),
(1, 'no_users_found', 'No users found'),
(1, 'add_first_user_to_get_started', 'Add your first user to get started.'),
(1, 'user', 'User'),
(1, 'employee_info', 'Employee Info'),
(1, 'last_login', 'Last Login'),
(1, 'created', 'Created'),
(1, 'no_employee_record', 'No employee record'),
(1, 'never', 'Never'),
(1, 'confirm_delete_user', 'Are you sure you want to delete user'),
(1, 'this_action_cannot_be_undone', 'This action cannot be undone.'),
(1, 'error_loading_reports', 'Error loading reports'),
(1, 'reports_analytics', 'Reports & Analytics'),
(1, 'export_pdf', 'Export PDF'),
(1, 'export_excel', 'Export Excel'),
(1, 'export_csv', 'Export CSV'),
(1, 'report_filters', 'Report Filters'),
(1, 'start_date', 'Start Date'),
(1, 'end_date', 'End Date'),
(1, 'report_type', 'Report Type'),
(1, 'overview', 'Overview'),
(1, 'financial', 'Financial'),
(1, 'employee', 'Employee'),
(1, 'contract', 'Contract'),
(1, 'machine', 'Machine'),
(1, 'generate_report', 'Generate Report'),
(1, 'total_companies', 'Total Companies'),
(1, 'active_subscriptions', 'Active Subscriptions'),
(1, 'total_revenue', 'Total Revenue'),
(1, 'total_hours', 'Total Hours'),
(1, 'total_employees', 'Total Employees'),
(1, 'total_earnings', 'Total Earnings'),
(1, 'total_expenses', 'Total Expenses'),
(1, 'revenue_trend', 'Revenue Trend'),
(1, 'earnings_trend', 'Earnings Trend'),
(1, 'working_hours', 'Working Hours'),
(1, 'detailed_report', 'Detailed Report'),
(1, 'revenue', 'Revenue'),
(1, 'earnings', 'Earnings'),
(1, 'worked', 'Worked'),
(1, 'remaining', 'Remaining'),
(1, 'attendance_record_deleted_successfully', 'Attendance record deleted successfully!'),
(1, 'employee_attendance', 'Employee Attendance'),
(1, 'add_attendance', 'Add Attendance'),
(1, 'total_records', 'Total Records'),
(1, 'present', 'Present'),
(1, 'late', 'Late'),
(1, 'absent', 'Absent'),
(1, 'search_by_employee_name_or_code', 'Search by employee name or code'),
(1, 'all_employees', 'All Employees'),
(1, 'leave', 'Leave'),
(1, 'attendance_records', 'Attendance Records'),
(1, 'no_attendance_records_found', 'No attendance records found'),
(1, 'add_first_attendance_record_to_get_started', 'Add your first attendance record to get started.'),
(1, 'check_in', 'Check In'),
(1, 'check_out', 'Check Out'),
(1, 'hours', 'Hours'),
(1, 'notes', 'Notes'),
(1, 'confirm_delete_attendance_record', 'Are you sure you want to delete attendance record for'),
(1, 'salary_payment_deleted_successfully', 'Salary payment deleted successfully!'),
(1, 'salary_payments', 'Salary Payments'),
(1, 'add_payment', 'Add Payment'),
(1, 'total_payments', 'Total Payments'),
(1, 'paid', 'Paid'),
(1, 'pending', 'Pending'),
(1, 'cancelled', 'Cancelled'),
(1, 'salary_payments_list', 'Salary Payments List'),
(1, 'no_salary_payments_found', 'No salary payments found'),
(1, 'add_first_salary_payment_to_get_started', 'Add your first salary payment to get started.'),
(1, 'payment_date', 'Payment Date'),
(1, 'period', 'Period'),
(1, 'payment_method', 'Payment Method'),
(1, 'days', 'days'),
(1, 'month', 'Month'),
(1, 'confirm_delete_salary_payment', 'Are you sure you want to delete salary payment for'),
(1, 'parking_space_management', 'Parking Space Management'),
(1, 'add_parking_space', 'Add Parking Space'),
(1, 'total_spaces', 'Total Spaces'),
(1, 'available', 'Available'),
(1, 'active_rentals', 'Active Rentals'),
(1, 'monthly_revenue', 'Monthly Revenue'),
(1, 'search_filter', 'Search & Filter'),
(1, 'search_by_space_name_or_code', 'Search by space name or code'),
(1, 'all_types', 'All Types'),
(1, 'machine', 'Machine'),
(1, 'container', 'Container'),
(1, 'equipment', 'Equipment'),
(1, 'occupied', 'Occupied'),
(1, 'parking_spaces', 'Parking Spaces'),
(1, 'no_parking_spaces_found', 'No parking spaces found.'),
(1, 'add_first_parking_space', 'Add First Parking Space'),
(1, 'space_code', 'Space Code'),
(1, 'space_name', 'Space Name'),
(1, 'type_size', 'Type & Size'),
(1, 'rate', 'Rate'),
(1, 'per_month', 'per month'),
(1, 'active', 'active'),
(1, 'quick_actions', 'Quick Actions'),
(1, 'add_new_parking_space', 'Add New Parking Space'),
(1, 'manage_all_rentals', 'Manage All Rentals'),
(1, 'create_new_rental', 'Create New Rental'),
(1, 'parking_reports', 'Parking Reports'),
(1, 'parking_statistics', 'Parking Statistics'),
(1, 'space_breakdown', 'Space Breakdown'),
(1, 'total', 'Total'),
(1, 'revenue_overview', 'Revenue Overview'),
(1, 'occupancy_rate', 'Occupancy Rate'),
(1, 'spaces_currently_occupied', 'Spaces currently occupied'),
(1, 'cannot_delete_rental_has_active_contracts', 'Cannot delete rental. It has {count} active contracts.'),
(1, 'area_rental_deleted_successfully', 'Area rental deleted successfully!'),
(1, 'area_rentals', 'Area Rentals'),
(1, 'add_rental_area', 'Add Rental Area'),
(1, 'total_rentals', 'Total Rentals'),
(1, 'rented', 'Rented'),
(1, 'search_by_name_code_or_location', 'Search by name, code, or location'),
(1, 'maintenance', 'Maintenance'),
(1, 'type', 'Type'),
(1, 'warehouse', 'Warehouse'),
(1, 'office', 'Office'),
(1, 'land', 'Land'),
(1, 'other', 'Other'),
(1, 'area_rentals_list', 'Area Rentals List'),
(1, 'no_area_rentals_found', 'No area rentals found'),
(1, 'add_first_rental_area_to_get_started', 'Add your first rental area to get started.'),
(1, 'area_name', 'Area Name'),
(1, 'location', 'Location'),
(1, 'contracts', 'Contracts'),
(1, 'created', 'Created'),
(1, 'confirm_delete_area_rental', 'Are you sure you want to delete area rental'),

-- Pashto translations (language_id = 2)
(2, 'dashboard', 'ډاشبورډ'),
(2, 'employees', 'کارمندان'),
(2, 'machines', 'ماشینونه'),
(2, 'contracts', 'تړونونه'),
(2, 'parking', 'پارک'),
(2, 'area_rentals', 'ساحه کرایه'),
(2, 'expenses', 'مصارف'),
(2, 'salary_payments', 'د معاش تادیه'),
(2, 'reports', 'راپورونه'),
(2, 'users', 'کارنونه'),
(2, 'settings', 'تنظیمات'),
(2, 'profile', 'پروفایل'),
(2, 'logout', 'وتل'),
(2, 'login', 'ننوتل'),
(2, 'register', 'ثبتول'),
(2, 'email', 'بریښنالیک'),
(2, 'password', 'پټ نوم'),
(2, 'remember_me', 'ما یاد کړه'),
(2, 'forgot_password', 'پټ نوم هیر شو؟'),
(2, 'submit', 'سپارل'),
(2, 'cancel', 'لغوه کول'),
(2, 'save', 'ساتل'),
(2, 'edit', 'سمول'),
(2, 'delete', 'ړنګول'),
(2, 'view', 'کتل'),
(2, 'add', 'زیاتول'),
(2, 'search', 'لټون'),
(2, 'filter', 'فلټر'),
(2, 'status', 'حالت'),
(2, 'active', 'فعال'),
(2, 'inactive', 'غیر فعال'),
(2, 'pending', 'په تمه'),
(2, 'completed', 'مکمل شوی'),
(2, 'success', 'بریالیتوب'),
(2, 'error', 'تیروتنه'),
(2, 'warning', 'خبرداری'),
(2, 'info', 'معلومات'),
(2, 'confirm_delete', 'آیا تاسو ډاډه یاست چې دا توکي ړنګ کړئ؟'),
(2, 'no_data', 'هیڅ معلومات و نه موندل شول'),
(2, 'loading', 'بار کول...'),
(2, 'back', 'شاته'),
(2, 'next', 'راتلونکی'),
(2, 'previous', 'پخوانی'),
(2, 'first', 'لومړی'),
(2, 'last', 'وروستی'),
(2, 'total', 'مجموعه'),
(2, 'amount', 'مقدار'),
(2, 'date', 'نیټه'),
(2, 'name', 'نوم'),
(2, 'phone', 'تلیفون'),
(2, 'position', 'موقف'),
(2, 'salary', 'معاش'),
(2, 'rate', 'نرخ'),
(2, 'hours', 'ساعتونه'),
(2, 'payment', 'تادیه'),
(2, 'notes', 'یادښتونه'),
(2, 'actions', 'کړنې'),
(2, 'currency', 'اسعار'),
(2, 'date_format', 'د نیټې بڼه'),
(2, 'language', 'ژبه'),
(2, 'timezone', 'د وخت ساحه'),
(2, 'company_settings', 'د شرکت تنظیمات'),
(2, 'timesheet', 'د وخت جدول'),
(2, 'work_hours', 'د کار ساعتونه'),
(2, 'daily_amount', 'ورځنی مقدار'),
(2, 'total_earned', 'مجموعه ګټه'),
(2, 'total_paid', 'مجموعه تادیه'),
(2, 'remaining_amount', 'پاتې مقدار'),
(2, 'progress', 'پرمختګ'),
(2, 'current_month', 'اوسنی میاشت'),
(2, 'contract_information', 'د تړون معلومات'),
(2, 'project', 'پروژه'),
(2, 'machine', 'ماشین'),
(2, 'employee', 'کارمند'),
(2, 'contract_type', 'د تړون ډول'),
(2, 'required_hours', 'اړین ساعتونه'),
(2, 'working_hours_per_day', 'د ورځې کار ساعتونه'),
(2, 'monthly_salary', 'میاشتنۍ معاش'),
(2, 'daily_rate', 'ورځنی نرخ'),
(2, 'leave_days', 'د رخصت ورځې'),
(2, 'working_days', 'د کار ورځې'),
(2, 'attendance', 'حضور'),
(2, 'payments', 'تادیې'),
(2, 'rentals', 'کرایې'),
(2, 'quick_actions', 'چټک کړنې'),
(2, 'statistics', 'احصایې'),
(2, 'recent_activity', 'نوي فعالیتونه'),
(2, 'system_settings', 'د سیسټم تنظیمات'),
(2, 'user_management', 'د کارن مدیریت'),
(2, 'company_management', 'د شرکت مدیریت'),
(2, 'subscription_plans', 'د ګډون پلانونه'),
(2, 'super_admin', 'سپر اډمین'),
(2, 'company_admin', 'د شرکت اډمین'),
(2, 'driver', 'چلوونکی'),
(2, 'driver_assistant', 'د چلوونکي مرستیال'),
(2, 'parking_user', 'د پارک کارن'),
(2, 'area_renter', 'د ساحې کرایه اخیستونکی'),
(2, 'container_renter', 'د کونټینر کرایه اخیستونکی'),
(2, 'pricing_plans', 'د نرخ پلانونه'),
(2, 'add_pricing_plan', 'د نرخ پلان زیاتول'),
(2, 'edit_pricing_plan', 'د نرخ پلان سمول'),
(2, 'plan_name', 'د پلان نوم'),
(2, 'plan_code', 'د پلان کوډ'),
(2, 'price', 'نرخ'),
(2, 'billing_cycle', 'د تادیه دوره'),
(2, 'features', 'ځانګړتیاوې'),
(2, 'is_popular', 'مشهور پلان'),
(2, 'is_active', 'فعال پلان'),
(2, 'max_employees', 'اعظمي کارمندان'),
(2, 'max_machines', 'اعظمي ماشینونه'),
(2, 'max_projects', 'اعظمي پروژې'),
(2, 'monthly', 'میاشتنۍ'),
(2, 'quarterly', 'دریمه میاشت'),
(2, 'yearly', 'کلنۍ'),
(2, 'unlimited', 'لامحدود'),
(2, 'basic', 'اساسي'),
(2, 'professional', 'مسلکي'),
(2, 'enterprise', 'سوداګریز'),
(2, 'employee_management', 'د کارمند مدیریت'),
(2, 'machine_tracking', 'د ماشین تعقیب'),
(2, 'basic_reports', 'اساسي راپورونه'),
(2, 'email_support', 'د بریښنالیک ملاتړ'),
(2, 'mobile_access', 'د موبایل لاسرسی'),
(2, 'advanced_analytics', 'پرمختللي تحلیلونه'),
(2, 'priority_support', 'د لومړیتوب ملاتړ'),
(2, 'api_access', 'د API لاسرسی'),
(2, 'custom_reports', 'دودیز راپورونه'),
(2, 'multi_currency_support', 'د ګڼو اسعارو ملاتړ'),
(2, 'unlimited_everything', 'لامحدود هر څه'),
(2, 'dedicated_support', 'ځانګړي ملاتړ'),
(2, 'custom_integrations', 'دودیز یوځای کول'),
(2, 'white_label_options', 'د سپین نښه اختیارونه'),
(2, 'advanced_security', 'پرمختللي امنیت'),
(2, 'most_popular', 'ډیر مشهور'),
(2, 'get_started', 'پیل کول'),
(2, 'choose_your_plan', 'خپل پلان وټاکئ'),
(2, 'flexible_pricing_plans', 'د انعطاف وړ نرخ پلانونه چې د ټولو اندازو د جوړښت شرکتونو لپاره ډیزاین شوي'),
(2, 'perfect_for_small_companies', 'د کوچنیو جوړښت شرکتونو لپاره کامل'),
(2, 'ideal_for_growing_businesses', 'د ودې موندونکو سوداګریزو لپاره مثالي'),
(2, 'complete_solution_large_companies', 'د لویو جوړښت شرکتونو لپاره کامل حل'),
(2, 'up_to_10_employees', 'تر ۱۰ کارمندانو پورې'),
(2, 'up_to_50_employees', 'تر ۵۰ کارمندانو پورې'),
(2, 'unlimited_employees', 'لامحدود کارمندان'),
(2, 'up_to_25_machines', 'تر ۲۵ ماشینونو پورې'),
(2, 'up_to_100_machines', 'تر ۱۰۰ ماشینونو پورې'),
(2, 'unlimited_machines', 'لامحدود ماشینونه'),
(2, 'everything_in_basic', 'هر څه په اساسي کې'),
(2, 'everything_in_professional', 'هر څه په مسلکي کې'),
(2, 'language_changed_successfully', 'ژبه په بریالیتوب سره بدله شوه'),
(2, 'failed_to_change_language', 'د ژبې بدلول ناکام شو'),
(2, 'invalid_language', 'ناسمه ژبه'),
(2, 'language_parameter_required', 'د ژبې پارامټر اړین دی'),
(2, 'pricing_plan_added_successfully', 'د نرخ پلان په بریالیتوب سره زیات شو!'),
(2, 'pricing_plan_updated_successfully', 'د نرخ پلان په بریالیتوب سره تازه شو!'),
(2, 'pricing_plan_deleted_successfully', 'د نرخ پلان په بریالیتوب سره ړنګ شو!'),
(2, 'cannot_delete_plan_in_use', 'دا پلان نشي ړنګ کولی ځکه چې {count} شرکتونه اوس کاروي.'),
(2, 'plan_code_already_exists', 'د پلان کوډ دمخه شتون لري. مهرباني وکړئ بل وټاکئ.'),
(2, 'price_must_be_positive', 'نرخ باید مثبت شمیره وي.'),
(2, 'field_required', 'ساحه "{field}" اړینه ده.'),
(2, 'please_fill_required_fields', 'مهرباني وکړئ ټول اړین ساحې ډک کړئ.'),
(2, 'price_must_be_greater_than_zero', 'نرخ باید له صفر څخه لوی وي.'),
(2, 'plan_code_format_error', 'د پلان کوډ باید یوازې د لویو تورو، شمیرو او لاندې کرښو لرونکی وي.'),
(2, 'companies_using_plan', 'پلان کاروونکي شرکتونه'),
(2, 'features_count', 'د ځانګړتیاوو شمیر'),
(2, 'plan_type', 'د پلان ډول'),
(2, 'current_plan_info', 'اوسني پلان معلومات'),
(2, 'plan_types', 'د پلان ډولونه'),
(2, 'billing_cycles', 'د تادیه دورې'),
(2, 'popular_features', 'مشهور ځانګړتیاوې'),
(2, 'tips', 'لارښوونې'),
(2, 'use_clear_descriptive_names', 'د واضحو، تشریحي پلان نومونو کارول'),
(2, 'set_reasonable_limits', 'د هرې کچې لپاره معقول محدودیتونه ټاکل'),
(2, 'highlight_key_features', 'د ځانګړتیاوو په تشریحاتو کې کلیدي ځانګړتیاوې روښانه کول'),
(2, 'mark_best_value_popular', 'خپل غوره ارزښت پلان د مشهور په توګه نښه کول'),
(2, 'manage_companies', 'د شرکتونو مدیریت'),
(2, 'add_company', 'شرکت زیاتول'),
(2, 'search_filter', 'لټون او فلټر'),
(2, 'search_by_company_name_email_code', 'د شرکت نوم، بریښنالیک یا کوډ په واسطه لټون'),
(2, 'all_status', 'ټول حالتونه'),
(2, 'active', 'فعال'),
(2, 'trial', 'آزمون'),
(2, 'suspended', 'درېدل'),
(2, 'cancelled', 'لغوه شوی'),
(2, 'all_plans', 'ټول پلانونه'),
(2, 'basic', 'اساسي'),
(2, 'professional', 'مسلکي'),
(2, 'enterprise', 'سوداګریز'),
(2, 'search', 'لټون'),
(2, 'clear', 'پاکول'),
(2, 'company_list', 'د شرکت لیست'),
(2, 'no_companies_found', 'هیڅ شرکتونه و نه موندل شول.'),
(2, 'add_first_company', 'لومړی شرکت زیاتول'),
(2, 'company_code', 'د شرکت کوډ'),
(2, 'company_name', 'د شرکت نوم'),
(2, 'contact', 'اړیکه'),
(2, 'subscription', 'ګډون'),
(2, 'usage', 'کارول'),
(2, 'status', 'حالت'),
(2, 'created', 'جوړ شوی'),
(2, 'actions', 'کړنې'),
(2, 'total_companies', 'ټول شرکتونه'),
(2, 'active_companies', 'فعال شرکتونه'),
(2, 'trial_companies', 'آزمون شرکتونه'),
(2, 'total_revenue', 'ټول عاید'),
(2, 'super_admin_dashboard', 'سپر اډمین ډاشبورډ'),
(2, 'active_subscriptions', 'فعال ګډونونه'),
(2, 'monthly_revenue', 'میاشتنۍ عاید'),
(2, 'manage_plans', 'پلانونه مدیریت کول'),
(2, 'view_payments', 'تادیې کتل'),
(2, 'recent_companies', 'نوي شرکتونه'),
(2, 'view_all_companies', 'ټول شرکتونه کتل'),
(2, 'recent_payments', 'نوي تادیې'),
(2, 'no_payments_found', 'هیڅ تادیې و نه موندل شول.'),
(2, 'view_all_payments', 'ټول تادیې کتل'),
(2, 'subscription_plan_statistics', 'د ګډون پلان احصایې'),
(2, 'no_subscription_data_available', 'هیڅ ګډون معلومات شتون نلري.'),
(2, 'plan', 'پلان'),
(2, 'companies', 'شرکتونه'),
(2, 'system_overview', 'د سیسټم عمومي کتنه'),
(2, 'system_information', 'د سیسټم معلومات'),
(2, 'total_users', 'ټول کارنونه'),
(2, 'quick_links', 'چټک لینکونه'),
(2, 'subscription_plans', 'د ګډون پلانونه'),
(2, 'payment_history', 'د تادیه تاریخچه'),
(2, 'platform_expenses', 'د پلاتفورم مصارف'),
(2, 'add_expense', 'مصرف زیاتول'),
(2, 'total_expenses', 'ټول مصارف'),
(2, 'total_usd', 'ټول USD'),
(2, 'total_afn', 'ټول AFN'),
(2, 'monthly_usd', 'میاشتنۍ USD'),
(2, 'monthly_afn', 'میاشتنۍ AFN'),
(2, 'monthly_count', 'میاشتنۍ شمیر'),
(2, 'total_expenses_by_currency', 'د اسعارو له مخې ټول مصارف'),
(2, 'expenses', 'مصارف'),
(2, 'no_expenses_found', 'هیڅ مصارف و نه موندل شول'),
(2, 'this_month_by_currency', 'د دې میاشتې له مخې د اسعارو'),
(2, 'monthly_total', 'میاشتنۍ مجموعه'),
(2, 'no_expenses_this_month', 'په دې میاشت کې هیڅ مصارف نشته'),
(2, 'search_by_code_description_notes', 'د کوډ، تشریح یا یادښتونو له مخې لټون'),
(2, 'all_types', 'ټول ډولونه'),
(2, 'office_supplies', 'د دفتر توکي'),
(2, 'utilities', 'خدمات'),
(2, 'rent', 'کرایه'),
(2, 'maintenance', 'ساتنه'),
(2, 'marketing', 'بازاریابی'),
(2, 'software', 'سافټویر'),
(2, 'travel', 'سفر'),
(2, 'other', 'نور'),
(2, 'from_date', 'له نیټې'),
(2, 'to_date', 'تر نیټې'),
(2, 'expense_code', 'د مصرف کوډ'),
(2, 'type', 'ډول'),
(2, 'description', 'تشریح'),
(2, 'amount', 'مقدار'),
(2, 'currency', 'اسعار'),
(2, 'date', 'نیټه'),
(2, 'payment_method', 'د تادیه طریقه'),
(2, 'receipt', 'رسید'),
(2, 'na', 'ن/م'),
(2, 'confirm_delete_expense', 'آیا تاسو ډاډه یاست چې دا مصرف ړنګ کړئ؟'),
(2, 'previous', 'پخوانی'),
(2, 'next', 'راتلونکی'),
(2, 'add_first_expense_to_get_started', 'خپل لومړی مصرف زیات کړئ تر څو پیل کړئ.'),
(2, 'platform_payments', 'د پلاتفورم تادیې'),
(2, 'add_payment', 'تادیه زیاتول'),
(2, 'export', 'صادرول'),
(2, 'total_payments', 'ټول تادیې'),
(2, 'usd_received', 'USD ترلاسه شوی'),
(2, 'afn_received', 'AFN ترلاسه شوی'),
(2, 'pending_usd', 'په تمه USD'),
(2, 'pending_afn', 'په تمه AFN'),
(2, 'this_month', 'دا میاشت'),
(2, 'search_by_payment_code_transaction_id_notes', 'د تادیه کوډ، معاملې ID یا یادښتونو له مخې لټون'),
(2, 'all_methods', 'ټول طریقه'),
(2, 'credit_card', 'کریډیټ کارت'),
(2, 'bank_transfer', 'بانکي لیږد'),
(2, 'cash', 'نغدي'),
(2, 'check', 'چیک'),
(2, 'paypal', 'پیپال'),
(2, 'all_companies', 'ټول شرکتونه'),
(2, 'payments', 'تادیې'),
(2, 'payments_from_companies_will_appear_here', 'د شرکتونو تادیې دلته به ښکاره شي.'),
(2, 'payment_code', 'د تادیه کوډ'),
(2, 'company', 'شرکت'),
(2, 'method', 'طریقه'),
(2, 'confirm_approve_payment', 'آیا تاسو ډاډه یاست چې دا تادیه تصویب کړئ؟'),
(2, 'failed', 'ناکام شوی'),
(2, 'please_enter_both_email_and_password', 'مهرباني وکړئ دواړه بریښنالیک او پټ نوم داخل کړئ.'),
(2, 'invalid_email_or_password', 'ناسم بریښنالیک یا پټ نوم.'),
(2, 'multi_tenant_construction_management', 'د ګڼو کرایه اخیستونکو جوړښت مدیریت'),
(2, 'email_address', 'د بریښنالیک پته'),
(2, 'demo_accounts', 'د نمونه حسابونه'),
(2, 'company_admin', 'د شرکت اډمین'),
(2, 'welcome', 'ښه راغلاست'),
(2, 'add_employee', 'کارمند زیاتول'),
(2, 'add_machine', 'ماشین زیاتول'),
(2, 'add_contract', 'تړون زیاتول'),
(2, 'active_employees', 'فعال کارمندان'),
(2, 'available_machines', 'شته ماشینونه'),
(2, 'active_contracts', 'فعال تړونونه'),
(2, 'contract_value', 'د تړون ارزښت'),
(2, 'monthly_salary', 'میاشتنۍ معاش'),
(2, 'working_days', 'د کار ورځې'),
(2, 'leave_days', 'د رخصت ورځې'),
(2, 'daily_rate', 'ورځنی نرخ'),
(2, 'active_rentals', 'فعال کرایې'),
(2, 'total_paid', 'ټول تادیه شوی'),
(2, 'remaining_amount', 'پاتې مقدار'),
(2, 'next_payment', 'راتلونکې تادیه'),
(2, 'manage_employees', 'کارمندان مدیریت کول'),
(2, 'manage_machines', 'ماشینونه مدیریت کول'),
(2, 'manage_contracts', 'تړونونه مدیریت کول'),
(2, 'manage_expenses', 'مصارف مدیریت کول'),
(2, 'view_attendance', 'حضور کتل'),
(2, 'view_salary', 'معاش کتل'),
(2, 'update_profile', 'پروفایل تازه کول'),
(2, 'view_rentals', 'کرایې کتل'),
(2, 'make_payment', 'تادیه کول'),
(2, 'welcome_to_app', 'ښه راغلاست {app_name} ته'),
(2, 'welcome_message', 'ښه راغلاست د جوړښت شرکت مدیریت سیسټم ته. مهرباني وکړئ د ځانګړو ځانګړتیاوو لپاره له خپل مدیر سره اړیکه ونیسئ.'),
(2, 'employee_deleted_successfully', 'کارمند په بریالیتوب سره ړنګ شو!'),
(2, 'employee_not_found_or_access_denied', 'کارمند و نه موندل شو یا لاسرسی رد شو.'),
(2, 'error_deleting_employee', 'د کارمند ړنګ کولو کې تیروتنه'),
(2, 'employees', 'کارمندان'),
(2, 'total_employees', 'ټول کارمندان'),
(2, 'drivers', 'چلوونکي'),
(2, 'assistants', 'مرستیالان'),
(2, 'filters', 'فلټرونه'),
(2, 'search_by_name_code_email', 'د نوم، کوډ یا بریښنالیک له مخې لټون'),
(2, 'position', 'موقف'),
(2, 'all_positions', 'ټول موقفونه'),
(2, 'driver', 'چلوونکی'),
(2, 'driver_assistant', 'د چلوونکي مرستیال'),
(2, 'machine_operator', 'د ماشین چلوونکی'),
(2, 'supervisor', 'څارونکی'),
(2, 'technician', 'تخنیکي'),
(2, 'inactive', 'غیرفعال'),
(2, 'employees_list', 'د کارمندان لیست'),
(2, 'export_options', 'د صادرولو اختیارونه'),
(2, 'export_to_csv', 'CSV ته صادرول'),
(2, 'export_to_pdf', 'PDF ته صادرول'),
(2, 'no_employees_found', 'هیڅ کارمندان و نه موندل شول'),
(2, 'add_first_employee_to_get_started', 'خپل لومړی کارمند زیات کړئ تر څو پیل کړئ.'),
(2, 'employee_code', 'د کارمند کوډ'),
(2, 'name', 'نوم'),
(2, 'email', 'بریښنالیک'),
(2, 'phone', 'تلیفون'),
(2, 'no_email', 'هیڅ بریښنالیک نشته'),
(2, 'no_phone', 'هیڅ تلیفون نشته'),
(2, 'confirm_delete_employee', 'آیا تاسو ډاډه یاست چې کارمند ړنګ کړئ'),
(2, 'this_action_cannot_be_undone', 'دا کړنه بېرته نه شي کولی.'),
(2, 'pdf_export_feature_coming_soon', 'د PDF صادرولو ځانګړتیا ژر راتلونکې ده!'),
(2, 'machine_management', 'د ماشین مدیریت'),
(2, 'total_machines', 'ټول ماشینونه'),
(2, 'available', 'شته'),
(2, 'in_use', 'په کار کې'),
(2, 'total_value', 'ټول ارزښت'),
(2, 'search_by_name_code_model', 'د نوم، کوډ یا ماډل له مخې لټون'),
(2, 'all_types', 'ټول ډولونه'),
(2, 'maintenance', 'ساتنه'),
(2, 'retired', 'تقاعد شوی'),
(2, 'machine_inventory', 'د ماشین انوینټري'),
(2, 'no_machines_found', 'هیڅ ماشینونه و نه موندل شول.'),
(2, 'add_first_machine', 'لومړی ماشین زیاتول'),
(2, 'machine_code', 'د ماشین کوډ'),
(2, 'name_model', 'نوم او ماډل'),
(2, 'specifications', 'ځانګړتیاوې'),
(2, 'value', 'ارزښت'),
(2, 'capacity', 'ظرفیت'),
(2, 'fuel', 'سوخت'),
(2, 'purchase', 'پیرود'),
(2, 'confirm_retire_machine', 'آیا تاسو ډاډه یاست چې دا ماشین تقاعد کړئ؟'),
(2, 'confirm_reactivate_machine', 'آیا تاسو ډاډه یاست چې دا ماشین بېرته فعال کړئ؟'),
(2, 'add_new_machine', 'نوی ماشین زیاتول'),
(2, 'manage_contracts', 'تړونونه مدیریت کول'),
(2, 'maintenance_schedule', 'د ساتنې پروګرام'),
(2, 'machine_reports', 'د ماشین راپورونه'),
(2, 'machine_statistics', 'د ماشین احصایې'),
(2, 'status_breakdown', 'د حالت تجزیه'),
(2, 'value_overview', 'د ارزښت کتنه'),
(2, 'average_value', 'منځنی ارزښت'),
(2, 'utilization_rate', 'د کارونې نرخ'),
(2, 'machines_currently_in_use', 'اوس په کار کې ماشینونه'),
(2, 'contract_management', 'د تړون مدیریت'),
(2, 'total_contracts', 'ټول تړونونه'),
(2, 'active_contracts', 'فعال تړونونه'),
(2, 'completed', 'پای ته رسیدلی'),
(2, 'monthly_contract_revenue', 'میاشتنۍ د تړون عاید'),
(2, 'contract_types', 'د تړون ډولونه'),
(2, 'search_by_code_project_machine', 'د کوډ، پروژه یا ماشین له مخې لټون'),
(2, 'hourly', 'ورځنی'),
(2, 'daily', 'روزانه'),
(2, 'monthly', 'میاشتنۍ'),
(2, 'cancelled', 'لغوه شوی'),
(2, 'contract_list', 'د تړون لیست'),
(2, 'no_contracts_found', 'هیڅ تړونونه و نه موندل شول.'),
(2, 'add_first_contract', 'لومړی تړون زیاتول'),
(2, 'contract_code', 'د تړون کوډ'),
(2, 'project_machine', 'پروژه او ماشین'),
(2, 'type_rate', 'ډول او نرخ'),
(2, 'progress', 'پرمختګ'),
(2, 'hr', 'ساعت'),
(2, 'day', 'ورځ'),
(2, 'month', 'میاشت'),
(2, 'hours', 'ساعتونه'),
(2, 'paid', 'تادیه شوی'),
(2, 'confirm_complete_contract', 'آیا تاسو ډاډه یاست چې دا تړون پای ته ورسوئ؟'),
(2, 'expense_management', 'د مصارف مدیریت'),
(2, 'add_expense', 'مصرف زیاتول'),
(2, 'monthly_expenses', 'میاشتنۍ مصارف'),
(2, 'top_category', 'غوره کټګوري'),
(2, 'recent_7_days', 'وروستي (۷ ورځې)'),
(2, 'search_by_description_code_reference', 'د تشریح، کوډ یا مرجع له مخې لټون'),
(2, 'all_categories', 'ټول کټګورۍ'),
(2, 'from_date', 'له نیټې'),
(2, 'to_date', 'تر نیټې'),
(2, 'expense_list', 'د مصارف لیست'),
(2, 'no_expenses_found', 'هیڅ مصارف و نه موندل شول.'),
(2, 'add_first_expense', 'لومړی مصرف زیاتول'),
(2, 'expense_code', 'د مصرف کوډ'),
(2, 'description', 'تشریح'),
(2, 'amount', 'مقدار'),
(2, 'date', 'نیټه'),
(2, 'payment_method', 'د تادیې طریقه'),
(2, 'reference', 'مرجع'),
(2, 'confirm_delete_expense', 'آیا تاسو ډاډه یاست چې دا مصرف ړنګ کړئ؟'),
(2, 'category_breakdown', 'د کټګوري تجزیه'),
(2, 'count', 'شمیر'),
(2, 'total_amount', 'ټول مقدار'),
(2, 'percentage', 'سلنه'),
(2, 'company_name_required', 'د شرکت نوم اړین دی.'),
(2, 'invalid_email_format', 'ناسم د بریښنالیک بڼه.'),
(2, 'company_information_updated_successfully', 'د شرکت معلومات په بریالیتوب سره تازه شول!'),
(2, 'company_preferences_updated_successfully', 'د شرکت غوره توبونه په بریالیتوب سره تازه شول!'),
(2, 'notification_settings_updated_successfully', 'د خبرتیا تنظیمات په بریالیتوب سره تازه شول!'),
(2, 'security_settings_updated_successfully', 'د امنیت تنظیمات په بریالیتوب سره تازه شول!'),
(2, 'integration_settings_updated_successfully', 'د یوځای کولو تنظیمات په بریالیتوب سره تازه شول!'),
(2, 'company_settings', 'د شرکت تنظیمات'),
(2, 'company_info', 'د شرکت معلومات'),
(2, 'preferences', 'غوره توبونه'),
(2, 'notifications', 'خبرتیاوې'),
(2, 'security', 'امنیت'),
(2, 'integrations', 'یوځای کول'),
(2, 'company_name', 'د شرکت نوم'),
(2, 'company_email', 'د شرکت بریښنالیک'),
(2, 'company_phone', 'د شرکت تلیفون'),
(2, 'company_website', 'د شرکت ویب پاڼه'),
(2, 'company_address', 'د شرکت پته'),
(2, 'company_description', 'د شرکت تشریح'),
(2, 'update_company_info', 'د شرکت معلومات تازه کول'),
(2, 'field_required', 'ساحه {field} اړینه ده.'),
(2, 'email_already_exists', 'بریښنالیک دمخه په سیسټم کې شتون لري.'),
(2, 'profile_updated_successfully', 'پروفایل په بریالیتوب سره تازه شو!'),
(2, 'current_password_incorrect', 'اوسنی پټ نوم ناسم دی.'),
(2, 'password_min_length', 'نوی پټ نوم باید لږترلږه ۶ توري ولري.'),
(2, 'passwords_do_not_match', 'نوي پټ نومونه سره نه برابريږي.'),
(2, 'password_changed_successfully', 'پټ نوم په بریالیتوب سره بدل شو!'),
(2, 'my_profile', 'زما پروفایل'),
(2, 'back_to_dashboard', 'ډاشبورډ ته بېرته'),
(2, 'profile_information', 'د پروفایل معلومات'),
(2, 'first_name', 'لومړی نوم'),
(2, 'last_name', 'وروستی نوم'),
(2, 'phone_number', 'د تلیفون شمیره'),
(2, 'change_password', 'پټ نوم بدلول'),
(2, 'current_password', 'اوسنی پټ نوم'),
(2, 'new_password', 'نوی پټ نوم'),
(2, 'confirm_password', 'پټ نوم تاییدول'),
(2, 'profile_summary', 'د پروفایل لنډیز'),
(2, 'member_since', 'له کومه چې غړی دی'),
(2, 'company_information', 'د شرکت معلومات'),
(2, 'company', 'شرکت'),
(2, 'plan', 'پلان'),
(2, 'no_plan', 'هیڅ پلان نشته'),
(2, 'trial_ends', 'د ازموینې پای'),
(2, 'recent_activity', 'وروستي فعالیت'),
(2, 'no_recent_activity', 'هیڅ وروستی فعالیت نشته'),
(2, 'cannot_delete_own_account', 'تاسو نشئ کولی خپل حساب ړنګ کړئ.'),
(2, 'cannot_delete_user_with_employee_record', 'کارن نشئ ړنګ کولی. د هغه سره تړلی کارمندان ریکارډ شتون لري.'),
(2, 'user_deleted_successfully', 'کارن په بریالیتوب سره ړنګ شو!'),
(2, 'user_management', 'د کارن مدیریت'),
(2, 'add_user', 'کارن زیاتول'),
(2, 'total_users', 'ټول کارن'),
(2, 'active_users', 'فعال کارن'),
(2, 'admins', 'ادارې'),
(2, 'drivers', 'چلوونکي'),
(2, 'filters', 'فلټرونه'),
(2, 'search_by_name_or_email', 'د نوم یا بریښنالیک له مخې لټون'),
(2, 'all_roles', 'ټول رولونه'),
(2, 'company_admin', 'د شرکت اداره'),
(2, 'driver', 'چلوونکی'),
(2, 'driver_assistant', 'د چلوونکي مرستیال'),
(2, 'parking_user', 'د پارک کارن'),
(2, 'area_renter', 'د ساحې کرایه اخیستونکی'),
(2, 'container_renter', 'د کنټینر کرایه اخیستونکی'),
(2, 'all_status', 'ټول حالتونه'),
(2, 'users_list', 'د کارن لیست'),
(2, 'export_options', 'د صادرات اختیارونه'),
(2, 'export_to_csv', 'CSV ته صادرول'),
(2, 'export_to_pdf', 'PDF ته صادرول'),
(2, 'no_users_found', 'هیڅ کارن و نه موندل شول'),
(2, 'add_first_user_to_get_started', 'لومړی کارن زیاتول ترڅو پیل وکړئ.'),
(2, 'user', 'کارن'),
(2, 'employee_info', 'د کارمندان معلومات'),
(2, 'last_login', 'وروستی ننوتل'),
(2, 'created', 'جوړ شوی'),
(2, 'no_employee_record', 'هیڅ کارمندان ریکارډ نشته'),
(2, 'never', 'هیڅکله'),
(2, 'confirm_delete_user', 'آیا تاسو ډاډه یاست چې کارن ړنګ کړئ'),
(2, 'this_action_cannot_be_undone', 'دا عمل نشي بیرته اړول کیدی.'),
(2, 'error_loading_reports', 'د راپورونو د لورډ کولو تیروتنه'),
(2, 'reports_analytics', 'راپورونه او تحلیلونه'),
(2, 'export_pdf', 'PDF ته صادرول'),
(2, 'export_excel', 'Excel ته صادرول'),
(2, 'export_csv', 'CSV ته صادرول'),
(2, 'report_filters', 'د راپور فلټرونه'),
(2, 'start_date', 'د پیل نیټه'),
(2, 'end_date', 'د پای نیټه'),
(2, 'report_type', 'د راپور ډول'),
(2, 'overview', 'لنډیز'),
(2, 'financial', 'مالي'),
(2, 'employee', 'کارمند'),
(2, 'contract', 'تړون'),
(2, 'machine', 'ماشین'),
(2, 'generate_report', 'راپور جوړول'),
(2, 'total_companies', 'ټول شرکتونه'),
(2, 'active_subscriptions', 'فعال ګډونونه'),
(2, 'total_revenue', 'ټول عاید'),
(2, 'total_hours', 'ټول ساعتونه'),
(2, 'total_employees', 'ټول کارمندان'),
(2, 'total_earnings', 'ټول عایدونه'),
(2, 'total_expenses', 'ټول مصارف'),
(2, 'revenue_trend', 'د عاید رجحان'),
(2, 'earnings_trend', 'د عایدونو رجحان'),
(2, 'working_hours', 'د کار ساعتونه'),
(2, 'detailed_report', 'تفصيلي راپور'),
(2, 'revenue', 'عاید'),
(2, 'earnings', 'عایدونه'),
(2, 'worked', 'کار شوی'),
(2, 'remaining', 'پاتې'),
(2, 'attendance_record_deleted_successfully', 'د حاضری ریکارډ په بریالیتوب سره ړنګ شو!'),
(2, 'employee_attendance', 'د کارمندان حاضری'),
(2, 'add_attendance', 'حاضری زیاتول'),
(2, 'total_records', 'ټول ریکارډونه'),
(2, 'present', 'حاضر'),
(2, 'late', 'وروسته'),
(2, 'absent', 'غائب'),
(2, 'search_by_employee_name_or_code', 'د کارمند نوم یا کوډ له مخې لټون'),
(2, 'all_employees', 'ټول کارمندان'),
(2, 'leave', 'رخصت'),
(2, 'attendance_records', 'د حاضری ریکارډونه'),
(2, 'no_attendance_records_found', 'هیڅ د حاضری ریکارډونه و نه موندل شول'),
(2, 'add_first_attendance_record_to_get_started', 'لومړی د حاضری ریکارډ زیاتول ترڅو پیل وکړئ.'),
(2, 'check_in', 'ننوتل'),
(2, 'check_out', 'وتل'),
(2, 'hours', 'ساعتونه'),
(2, 'notes', 'یادښتونه'),
(2, 'confirm_delete_attendance_record', 'آیا تاسو ډاډه یاست چې د حاضری ریکارډ ړنګ کړئ'),
(2, 'salary_payment_deleted_successfully', 'د معاش تادیې په بریالیتوب سره ړنګ شو!'),
(2, 'salary_payments', 'د معاش تادیې'),
(2, 'add_payment', 'تادیه زیاتول'),
(2, 'total_payments', 'ټول تادیې'),
(2, 'paid', 'تادیه شوی'),
(2, 'pending', 'په تمه'),
(2, 'cancelled', 'لغوه شوی'),
(2, 'salary_payments_list', 'د معاش تادیو لیست'),
(2, 'no_salary_payments_found', 'هیڅ د معاش تادیې و نه موندل شول'),
(2, 'add_first_salary_payment_to_get_started', 'لومړی د معاش تادیه زیاتول ترڅو پیل وکړئ.'),
(2, 'payment_date', 'د تادیې نیټه'),
(2, 'period', 'موده'),
(2, 'payment_method', 'د تادیې طریقه'),
(2, 'days', 'ورځې'),
(2, 'month', 'میاشت'),
(2, 'confirm_delete_salary_payment', 'آیا تاسو ډاډه یاست چې د معاش تادیه ړنګ کړئ'),
(2, 'parking_space_management', 'د پارکینګ ځای مدیریت'),
(2, 'add_parking_space', 'د پارکینګ ځای زیاتول'),
(2, 'total_spaces', 'ټول ځایونه'),
(2, 'available', 'شته'),
(2, 'active_rentals', 'فعال کرایه'),
(2, 'monthly_revenue', 'میاشتنی عاید'),
(2, 'search_filter', 'لټون او فلټر'),
(2, 'search_by_space_name_or_code', 'د ځای نوم یا کوډ له مخې لټون'),
(2, 'all_types', 'ټول ډولونه'),
(2, 'machine', 'ماشین'),
(2, 'container', 'کانټینر'),
(2, 'equipment', 'تجهیزات'),
(2, 'occupied', 'نیول شوی'),
(2, 'parking_spaces', 'د پارکینګ ځایونه'),
(2, 'no_parking_spaces_found', 'هیڅ د پارکینګ ځایونه و نه موندل شول.'),
(2, 'add_first_parking_space', 'لومړی د پارکینګ ځای زیاتول'),
(2, 'space_code', 'د ځای کوډ'),
(2, 'space_name', 'د ځای نوم'),
(2, 'type_size', 'ډول او اندازه'),
(2, 'rate', 'نرخ'),
(2, 'per_month', 'په میاشت کې'),
(2, 'active', 'فعال'),
(2, 'quick_actions', 'چټک عملونه'),
(2, 'add_new_parking_space', 'نوی د پارکینګ ځای زیاتول'),
(2, 'manage_all_rentals', 'ټول کرایه مدیریت کول'),
(2, 'create_new_rental', 'نوی کرایه جوړول'),
(2, 'parking_reports', 'د پارکینګ راپورونه'),
(2, 'parking_statistics', 'د پارکینګ احصائیه'),
(2, 'space_breakdown', 'د ځای تجزیه'),
(2, 'total', 'ټول'),
(2, 'revenue_overview', 'د عاید لنډیز'),
(2, 'occupancy_rate', 'د نیولو نرخ'),
(2, 'spaces_currently_occupied', 'اوس مهال نیول شوي ځایونه'),
(2, 'cannot_delete_rental_has_active_contracts', 'کرایه نشي ړنګ کولی. د {count} فعال تړونونه لري.'),
(2, 'area_rental_deleted_successfully', 'د ساحې کرایه په بریالیتوب سره ړنګ شو!'),
(2, 'area_rentals', 'د ساحې کرایه'),
(2, 'add_rental_area', 'د کرایه ساحه زیاتول'),
(2, 'total_rentals', 'ټول کرایه'),
(2, 'rented', 'کرایه شوی'),
(2, 'search_by_name_code_or_location', 'د نوم، کوډ یا ځای له مخې لټون'),
(2, 'maintenance', 'ساتنه'),
(2, 'type', 'ډول'),
(2, 'warehouse', 'ګودام'),
(2, 'office', 'دفتر'),
(2, 'land', 'ځمکه'),
(2, 'other', 'نور'),
(2, 'area_rentals_list', 'د ساحې کرایه لیست'),
(2, 'no_area_rentals_found', 'هیڅ د ساحې کرایه و نه موندل شول'),
(2, 'add_first_rental_area_to_get_started', 'لومړی د کرایه ساحه زیاتول ترڅو پیل وکړئ.'),
(2, 'area_name', 'د ساحې نوم'),
(2, 'location', 'ځای'),
(2, 'contracts', 'تړونونه'),
(2, 'created', 'جوړ شوی'),
(2, 'confirm_delete_area_rental', 'آیا تاسو ډاډه یاست چې د ساحې کرایه ړنګ کړئ'),
(2, 'for_small_companies', 'د کوچنیو شرکتونو لپاره'),
(2, 'for_growing_businesses', 'د ودې موندونکو سوداګریزو لپاره'),
(2, 'for_large_companies', 'د لویو شرکتونو لپاره'),
(2, 'billed_every_month', 'هر میاشت تادیه کول'),
(2, 'billed_every_3_months', 'هر ۳ میاشت تادیه کول'),
(2, 'billed_annually', 'کلنۍ تادیه کول'),
(2, 'reports_analytics', 'راپورونه او تحلیلونه'),
(2, 'customer_support', 'د پیرودونکي ملاتړ'),
(2, 'api_access', 'د API لاسرسی'),
(2, 'total_plans', 'مجموعه پلانونه'),
(2, 'active_plans', 'فعال پلانونه'),
(2, 'popular_plans', 'مشهور پلانونه'),
(2, 'average_price', 'منځنی نرخ'),
(2, 'all_status', 'ټول حالتونه'),
(2, 'no_pricing_plans_found', 'هیڅ نرخ پلانونه و نه موندل شول'),
(2, 'add_first_pricing_plan', 'د پیل لپاره خپل لومړی نرخ پلان زیات کړئ.'),
(2, 'pricing_plans_management', 'د نرخ پلانونو مدیریت'),
(2, 'search_by_plan_name', 'د پلان نوم، کوډ یا تشریح له مخې لټون'),
(2, 'select_cycle', 'دوره وټاکئ'),
(2, 'unique_identifier', 'د پلان لپاره ځانګړي پېژندونکي'),
(2, 'brief_description', 'د پلان لنډه تشریح'),
(2, 'plan_limits', 'د پلان محدودیتونه'),
(2, 'plan_features', 'د پلان ځانګړتیاوې'),
(2, 'plan_settings', 'د پلان تنظیمات'),
(2, 'enter_features_one_per_line', 'ځانګړتیاوې دننه کړئ، هر یو په یوه کرښه کې'),
(2, 'popular_plans_highlighted', 'مشهور پلانونه په لینډینګ پاڼه کې روښانه کول'),
(2, 'inactive_plans_not_shown', 'غیر فعال پلانونه به پیرودونکو ته نه ښودل کیږي'),
(2, 'update_pricing_plan', 'د نرخ پلان تازه کول'),
(2, 'back_to_pricing_plans', 'د نرخ پلانونو ته شاته'),
(2, 'back_to_plan', 'د پلان ته شاته'),
(2, 'plan_details', 'د پلان تفصیلات'),
(2, 'plan_summary', 'د پلان لنډیز'),
(2, 'quick_actions', 'چټک کړنې'),
(2, 'plan_statistics', 'د پلان احصایې'),
(2, 'information', 'معلومات'),
(2, 'current_plan_info', 'اوسني پلان معلومات'),
(2, 'popular_plan', 'مشهور پلان'),

-- Dari translations (language_id = 3)
(3, 'dashboard', 'داشبورد'),
(3, 'employees', 'کارمندان'),
(3, 'machines', 'ماشین‌ها'),
(3, 'contracts', 'قراردادها'),
(3, 'parking', 'پارکینگ'),
(3, 'area_rentals', 'اجاره فضا'),
(3, 'expenses', 'هزینه‌ها'),
(3, 'salary_payments', 'پرداخت حقوق'),
(3, 'reports', 'گزارشات'),
(3, 'users', 'کاربران'),
(3, 'settings', 'تنظیمات'),
(3, 'profile', 'پروفایل'),
(3, 'logout', 'خروج'),
(3, 'login', 'ورود'),
(3, 'register', 'ثبت نام'),
(3, 'email', 'ایمیل'),
(3, 'password', 'رمز عبور'),
(3, 'remember_me', 'مرا به خاطر بسپار'),
(3, 'forgot_password', 'رمز عبور را فراموش کرده‌اید؟'),
(3, 'submit', 'ارسال'),
(3, 'cancel', 'لغو'),
(3, 'save', 'ذخیره'),
(3, 'edit', 'ویرایش'),
(3, 'delete', 'حذف'),
(3, 'view', 'مشاهده'),
(3, 'add', 'افزودن'),
(3, 'search', 'جستجو'),
(3, 'filter', 'فیلتر'),
(3, 'status', 'وضعیت'),
(3, 'active', 'فعال'),
(3, 'inactive', 'غیرفعال'),
(3, 'pending', 'در انتظار'),
(3, 'completed', 'تکمیل شده'),
(3, 'success', 'موفقیت'),
(3, 'error', 'خطا'),
(3, 'warning', 'هشدار'),
(3, 'info', 'اطلاعات'),
(3, 'confirm_delete', 'آیا مطمئن هستید که می‌خواهید این مورد را حذف کنید؟'),
(3, 'no_data', 'داده‌ای یافت نشد'),
(3, 'loading', 'در حال بارگذاری...'),
(3, 'back', 'بازگشت'),
(3, 'next', 'بعدی'),
(3, 'previous', 'قبلی'),
(3, 'first', 'اول'),
(3, 'last', 'آخر'),
(3, 'total', 'مجموع'),
(3, 'amount', 'مبلغ'),
(3, 'date', 'تاریخ'),
(3, 'name', 'نام'),
(3, 'phone', 'تلفن'),
(3, 'position', 'سمت'),
(3, 'salary', 'حقوق'),
(3, 'rate', 'نرخ'),
(3, 'hours', 'ساعت'),
(3, 'payment', 'پرداخت'),
(3, 'notes', 'یادداشت‌ها'),
(3, 'actions', 'عملیات'),
(3, 'currency', 'ارز'),
(3, 'date_format', 'فرمت تاریخ'),
(3, 'language', 'زبان'),
(3, 'timezone', 'منطقه زمانی'),
(3, 'company_settings', 'تنظیمات شرکت'),
(3, 'timesheet', 'برگه زمان'),
(3, 'work_hours', 'ساعت کار'),
(3, 'daily_amount', 'مبلغ روزانه'),
(3, 'total_earned', 'کل درآمد'),
(3, 'total_paid', 'کل پرداخت شده'),
(3, 'remaining_amount', 'مبلغ باقی‌مانده'),
(3, 'progress', 'پیشرفت'),
(3, 'current_month', 'ماه جاری'),
(3, 'contract_information', 'اطلاعات قرارداد'),
(3, 'project', 'پروژه'),
(3, 'machine', 'ماشین'),
(3, 'employee', 'کارمند'),
(3, 'contract_type', 'نوع قرارداد'),
(3, 'required_hours', 'ساعت مورد نیاز'),
(3, 'working_hours_per_day', 'ساعت کار روزانه'),
(3, 'monthly_salary', 'حقوق ماهانه'),
(3, 'daily_rate', 'نرخ روزانه'),
(3, 'leave_days', 'روزهای مرخصی'),
(3, 'working_days', 'روزهای کاری'),
(3, 'attendance', 'حضور'),
(3, 'payments', 'پرداخت‌ها'),
(3, 'rentals', 'اجاره‌ها'),
(3, 'quick_actions', 'عملیات سریع'),
(3, 'statistics', 'آمار'),
(3, 'recent_activity', 'فعالیت‌های اخیر'),
(3, 'system_settings', 'تنظیمات سیستم'),
(3, 'user_management', 'مدیریت کاربران'),
(3, 'company_management', 'مدیریت شرکت'),
(3, 'subscription_plans', 'طرح‌های اشتراک'),
(3, 'super_admin', 'مدیر کل'),
(3, 'company_admin', 'مدیر شرکت'),
(3, 'driver', 'راننده'),
(3, 'driver_assistant', 'دستیار راننده'),
(3, 'parking_user', 'کاربر پارکینگ'),
(3, 'area_renter', 'اجاره‌کننده فضا'),
(3, 'container_renter', 'اجاره‌کننده کانتینر'),
(3, 'pricing_plans', 'طرح‌های قیمت‌گذاری'),
(3, 'add_pricing_plan', 'افزودن طرح قیمت‌گذاری'),
(3, 'edit_pricing_plan', 'ویرایش طرح قیمت‌گذاری'),
(3, 'plan_name', 'نام طرح'),
(3, 'plan_code', 'کد طرح'),
(3, 'price', 'قیمت'),
(3, 'billing_cycle', 'چرخه صورتحساب'),
(3, 'features', 'ویژگی‌ها'),
(3, 'is_popular', 'طرح محبوب'),
(3, 'is_active', 'طرح فعال'),
(3, 'max_employees', 'حداکثر کارمندان'),
(3, 'max_machines', 'حداکثر ماشین‌ها'),
(3, 'max_projects', 'حداکثر پروژه‌ها'),
(3, 'monthly', 'ماهانه'),
(3, 'quarterly', 'سه‌ماهه'),
(3, 'yearly', 'سالانه'),
(3, 'unlimited', 'نامحدود'),
(3, 'basic', 'پایه'),
(3, 'professional', 'حرفه‌ای'),
(3, 'enterprise', 'شرکتی'),
(3, 'employee_management', 'مدیریت کارمندان'),
(3, 'machine_tracking', 'پیگیری ماشین'),
(3, 'basic_reports', 'گزارشات پایه'),
(3, 'email_support', 'پشتیبانی ایمیل'),
(3, 'mobile_access', 'دسترسی موبایل'),
(3, 'advanced_analytics', 'تحلیل‌های پیشرفته'),
(3, 'priority_support', 'پشتیبانی اولویت'),
(3, 'api_access', 'دسترسی API'),
(3, 'custom_reports', 'گزارشات سفارشی'),
(3, 'multi_currency_support', 'پشتیبانی چند ارزی'),
(3, 'unlimited_everything', 'همه چیز نامحدود'),
(3, 'dedicated_support', 'پشتیبانی اختصاصی'),
(3, 'custom_integrations', 'ادغام‌های سفارشی'),
(3, 'white_label_options', 'گزینه‌های برند سفید'),
(3, 'advanced_security', 'امنیت پیشرفته'),
(3, 'most_popular', 'محبوب‌ترین'),
(3, 'get_started', 'شروع کنید'),
(3, 'choose_your_plan', 'طرح خود را انتخاب کنید'),
(3, 'flexible_pricing_plans', 'طرح‌های قیمت‌گذاری انعطاف‌پذیر طراحی شده برای شرکت‌های ساختمانی در تمام اندازه‌ها'),
(3, 'perfect_for_small_companies', 'کامل برای شرکت‌های ساختمانی کوچک'),
(3, 'ideal_for_growing_businesses', 'ایده‌آل برای کسب‌وکارهای در حال رشد'),
(3, 'complete_solution_large_companies', 'راه‌حل کامل برای شرکت‌های ساختمانی بزرگ'),
(3, 'up_to_10_employees', 'تا ۱۰ کارمند'),
(3, 'up_to_50_employees', 'تا ۵۰ کارمند'),
(3, 'unlimited_employees', 'کارمندان نامحدود'),
(3, 'up_to_25_machines', 'تا ۲۵ ماشین'),
(3, 'up_to_100_machines', 'تا ۱۰۰ ماشین'),
(3, 'unlimited_machines', 'ماشین‌های نامحدود'),
(3, 'everything_in_basic', 'همه چیز در پایه'),
(3, 'everything_in_professional', 'همه چیز در حرفه‌ای'),
(3, 'language_changed_successfully', 'زبان با موفقیت تغییر کرد'),
(3, 'failed_to_change_language', 'تغییر زبان ناموفق بود'),
(3, 'invalid_language', 'زبان نامعتبر'),
(3, 'language_parameter_required', 'پارامتر زبان مورد نیاز است'),
(3, 'pricing_plan_added_successfully', 'طرح قیمت‌گذاری با موفقیت اضافه شد!'),
(3, 'pricing_plan_updated_successfully', 'طرح قیمت‌گذاری با موفقیت به‌روزرسانی شد!'),
(3, 'pricing_plan_deleted_successfully', 'طرح قیمت‌گذاری با موفقیت حذف شد!'),
(3, 'cannot_delete_plan_in_use', 'نمی‌توان این طرح را حذف کرد زیرا {count} شرکت در حال حاضر از آن استفاده می‌کنند.'),
(3, 'plan_code_already_exists', 'کد طرح قبلاً وجود دارد. لطفاً مورد دیگری انتخاب کنید.'),
(3, 'price_must_be_positive', 'قیمت باید عدد مثبت باشد.'),
(3, 'field_required', 'فیلد "{field}" مورد نیاز است.'),
(3, 'please_fill_required_fields', 'لطفاً تمام فیلدهای مورد نیاز را پر کنید.'),
(3, 'price_must_be_greater_than_zero', 'قیمت باید بزرگتر از صفر باشد.'),
(3, 'plan_code_format_error', 'کد طرح باید فقط شامل حروف بزرگ، اعداد و خط زیر باشد.'),
(3, 'companies_using_plan', 'شرکت‌های استفاده‌کننده'),
(3, 'features_count', 'تعداد ویژگی‌ها'),
(3, 'plan_type', 'نوع طرح'),
(3, 'current_plan_info', 'اطلاعات طرح فعلی'),
(3, 'plan_types', 'انواع طرح'),
(3, 'billing_cycles', 'چرخه‌های صورتحساب'),
(3, 'popular_features', 'ویژگی‌های محبوب'),
(3, 'tips', 'نکات'),
(3, 'use_clear_descriptive_names', 'استفاده از نام‌های واضح و توصیفی طرح'),
(3, 'set_reasonable_limits', 'تعیین محدودیت‌های معقول برای هر سطح'),
(3, 'highlight_key_features', 'برجسته کردن ویژگی‌های کلیدی در توضیحات'),
(3, 'mark_best_value_popular', 'علامت‌گذاری بهترین طرح ارزش به عنوان محبوب'),
(3, 'for_small_companies', 'برای شرکت‌های کوچک'),
(3, 'for_growing_businesses', 'برای کسب‌وکارهای در حال رشد'),
(3, 'for_large_companies', 'برای شرکت‌های بزرگ'),
(3, 'billed_every_month', 'صورتحساب هر ماه'),
(3, 'billed_every_3_months', 'صورتحساب هر ۳ ماه'),
(3, 'billed_annually', 'صورتحساب سالانه'),
(3, 'reports_analytics', 'گزارشات و تحلیل‌ها'),
(3, 'customer_support', 'پشتیبانی مشتری'),
(3, 'api_access', 'دسترسی API'),
(3, 'total_plans', 'کل طرح‌ها'),
(3, 'active_plans', 'طرح‌های فعال'),
(3, 'popular_plans', 'طرح‌های محبوب'),
(3, 'average_price', 'قیمت متوسط'),
(3, 'all_status', 'تمام وضعیت‌ها'),
(3, 'no_pricing_plans_found', 'هیچ طرح قیمت‌گذاری یافت نشد'),
(3, 'add_first_pricing_plan', 'اولین طرح قیمت‌گذاری خود را برای شروع اضافه کنید.'),
(3, 'pricing_plans_management', 'مدیریت طرح‌های قیمت‌گذاری'),
(3, 'search_by_plan_name', 'جستجو بر اساس نام طرح، کد یا توضیحات'),
(3, 'select_cycle', 'انتخاب چرخه'),
(3, 'unique_identifier', 'شناسه منحصر به فرد برای طرح'),
(3, 'brief_description', 'توضیح مختصر طرح'),
(3, 'plan_limits', 'محدودیت‌های طرح'),
(3, 'plan_features', 'ویژگی‌های طرح'),
(3, 'plan_settings', 'تنظیمات طرح'),
(3, 'enter_features_one_per_line', 'ویژگی‌ها را وارد کنید، هر کدام در یک خط'),
(3, 'popular_plans_highlighted', 'طرح‌های محبوب در صفحه اصلی برجسته می‌شوند'),
(3, 'inactive_plans_not_shown', 'طرح‌های غیرفعال به مشتریان نشان داده نمی‌شوند'),
(3, 'update_pricing_plan', 'به‌روزرسانی طرح قیمت‌گذاری'),
(3, 'back_to_pricing_plans', 'بازگشت به طرح‌های قیمت‌گذاری'),
(3, 'back_to_plan', 'بازگشت به طرح'),
(3, 'plan_details', 'جزئیات طرح'),
(3, 'plan_summary', 'خلاصه طرح'),
(3, 'quick_actions', 'عملیات سریع'),
(3, 'plan_statistics', 'آمار طرح'),
(3, 'information', 'اطلاعات'),
(3, 'current_plan_info', 'اطلاعات طرح فعلی'),
(3, 'popular_plan', 'طرح محبوب');