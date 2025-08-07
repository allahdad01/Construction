-- Enhance Area Rentals System
-- This script adds advanced features to the area rentals system

-- Add new columns to rental_areas table for better area management
ALTER TABLE rental_areas 
ADD COLUMN description TEXT AFTER area_type,
ADD COLUMN location_details TEXT AFTER description,
ADD COLUMN amenities TEXT AFTER location_details,
ADD COLUMN restrictions TEXT AFTER amenities,
ADD COLUMN currency VARCHAR(3) DEFAULT 'USD' AFTER monthly_rate,
ADD COLUMN capacity INT DEFAULT 1 AFTER currency,
ADD COLUMN area_size_sqm DECIMAL(10,2) AFTER capacity,
ADD COLUMN has_electricity BOOLEAN DEFAULT FALSE AFTER area_size_sqm,
ADD COLUMN has_water BOOLEAN DEFAULT FALSE AFTER has_electricity,
ADD COLUMN has_security BOOLEAN DEFAULT FALSE AFTER has_water,
ADD COLUMN has_parking BOOLEAN DEFAULT FALSE AFTER has_security,
ADD COLUMN has_loading_dock BOOLEAN DEFAULT FALSE AFTER has_parking,
ADD COLUMN is_covered BOOLEAN DEFAULT FALSE AFTER has_loading_dock,
ADD COLUMN max_vehicle_size VARCHAR(50) AFTER is_covered,
ADD COLUMN operating_hours VARCHAR(100) AFTER max_vehicle_size,
ADD COLUMN contact_person VARCHAR(100) AFTER operating_hours,
ADD COLUMN contact_phone VARCHAR(20) AFTER contact_person,
ADD COLUMN contact_email VARCHAR(100) AFTER contact_phone;

-- Add new columns to area_rentals table for better rental management
ALTER TABLE area_rentals 
ADD COLUMN rental_type VARCHAR(20) DEFAULT 'standard' AFTER purpose,
ADD COLUMN business_type VARCHAR(50) AFTER rental_type,
ADD COLUMN expected_income DECIMAL(12,2) AFTER business_type,
ADD COLUMN security_deposit DECIMAL(10,2) DEFAULT 0 AFTER expected_income,
ADD COLUMN deposit_paid DECIMAL(10,2) DEFAULT 0 AFTER security_deposit,
ADD COLUMN currency VARCHAR(3) DEFAULT 'USD' AFTER deposit_paid,
ADD COLUMN payment_frequency VARCHAR(20) DEFAULT 'monthly' AFTER currency,
ADD COLUMN late_fee_percentage DECIMAL(5,2) DEFAULT 5.00 AFTER payment_frequency,
ADD COLUMN grace_period_days INT DEFAULT 5 AFTER late_fee_percentage,
ADD COLUMN auto_renewal BOOLEAN DEFAULT FALSE AFTER grace_period_days,
ADD COLUMN renewal_notice_days INT DEFAULT 30 AFTER auto_renewal,
ADD COLUMN special_conditions TEXT AFTER renewal_notice_days,
ADD COLUMN emergency_contact VARCHAR(100) AFTER special_conditions,
ADD COLUMN emergency_phone VARCHAR(20) AFTER emergency_contact,
ADD COLUMN insurance_required BOOLEAN DEFAULT FALSE AFTER emergency_phone,
ADD COLUMN insurance_provider VARCHAR(100) AFTER insurance_required,
ADD COLUMN insurance_policy_number VARCHAR(50) AFTER insurance_provider,
ADD COLUMN insurance_expiry_date DATE AFTER insurance_policy_number,
ADD COLUMN permit_required BOOLEAN DEFAULT FALSE AFTER insurance_expiry_date,
ADD COLUMN permit_number VARCHAR(50) AFTER permit_required,
ADD COLUMN permit_expiry_date DATE AFTER permit_number,
ADD COLUMN inspection_date DATE AFTER permit_expiry_date,
ADD COLUMN inspection_status VARCHAR(20) DEFAULT 'pending' AFTER inspection_date,
ADD COLUMN inspection_notes TEXT AFTER inspection_status;

-- Create area_rental_payments table for payment tracking
CREATE TABLE area_rental_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    area_rental_id INT NOT NULL,
    payment_code VARCHAR(20) NOT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    payment_method VARCHAR(20) NOT NULL,
    payment_type ENUM('rent', 'deposit', 'late_fee', 'other') DEFAULT 'rent',
    reference_number VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (area_rental_id) REFERENCES area_rentals(id) ON DELETE CASCADE,
    UNIQUE KEY unique_payment_per_company (company_id, payment_code)
);

-- Create area_rental_documents table for document management
CREATE TABLE area_rental_documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    area_rental_id INT NOT NULL,
    document_type VARCHAR(50) NOT NULL,
    document_name VARCHAR(100) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_size INT,
    mime_type VARCHAR(100),
    uploaded_by INT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (area_rental_id) REFERENCES area_rentals(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Create area_rental_maintenance table for maintenance tracking
CREATE TABLE area_rental_maintenance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    area_rental_id INT NOT NULL,
    maintenance_type VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    reported_date DATE NOT NULL,
    scheduled_date DATE,
    completed_date DATE,
    cost DECIMAL(10,2),
    status VARCHAR(20) DEFAULT 'pending',
    assigned_to VARCHAR(100),
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (area_rental_id) REFERENCES area_rentals(id) ON DELETE CASCADE
);

-- Create area_rental_visits table for visit tracking
CREATE TABLE area_rental_visits (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    area_rental_id INT NOT NULL,
    visit_date DATE NOT NULL,
    visit_time TIME,
    visitor_name VARCHAR(100) NOT NULL,
    visitor_contact VARCHAR(100),
    purpose VARCHAR(200),
    duration_minutes INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (area_rental_id) REFERENCES area_rentals(id) ON DELETE CASCADE
);

-- Add indexes for better performance
CREATE INDEX idx_area_rentals_status ON area_rentals(status);
CREATE INDEX idx_area_rentals_dates ON area_rentals(start_date, end_date);
CREATE INDEX idx_area_rentals_client ON area_rentals(client_name);
CREATE INDEX idx_rental_areas_status ON rental_areas(status);
CREATE INDEX idx_rental_areas_type ON rental_areas(area_type);
CREATE INDEX idx_area_rental_payments_date ON area_rental_payments(payment_date);
CREATE INDEX idx_area_rental_payments_type ON area_rental_payments(payment_type);

-- Insert sample data for enhanced area types
INSERT INTO rental_areas (company_id, area_code, area_name, area_type, description, location_details, amenities, restrictions, monthly_rate, currency, capacity, area_size_sqm, has_electricity, has_water, has_security, has_parking, has_loading_dock, is_covered, max_vehicle_size, operating_hours, contact_person, contact_phone, contact_email) VALUES
(1, 'AREA001', 'Main Commercial Zone A', 'commercial', 'Prime commercial area suitable for retail shops, restaurants, and service businesses', 'Located at the main entrance with high foot traffic', 'Electricity, Water, Security cameras, Parking for 10 vehicles, Loading dock', 'No overnight storage, No hazardous materials, Business hours only', 2500.00, 'USD', 5, 200.00, TRUE, TRUE, TRUE, TRUE, TRUE, TRUE, 'Large trucks', '8:00 AM - 8:00 PM', 'John Manager', '+1234567890', 'john@company.com'),
(1, 'AREA002', 'Industrial Storage Zone', 'industrial', 'Large industrial area perfect for storage, workshops, and manufacturing', 'Back section with easy truck access', 'Heavy electricity, Industrial water, Security fencing, Large parking area, Multiple loading docks', '24/7 access allowed, Heavy machinery permitted, Hazardous materials with permit', 3500.00, 'USD', 10, 500.00, TRUE, TRUE, TRUE, TRUE, TRUE, FALSE, 'Heavy machinery', '24/7', 'Sarah Industrial', '+1234567891', 'sarah@company.com'),
(1, 'AREA003', 'Residential Living Space', 'residential', 'Comfortable living area suitable for temporary housing or accommodation', 'Quiet residential section with garden view', 'Basic electricity, Water, Security, Parking for 2 vehicles, Garden access', 'Residential use only, No commercial activities, Quiet hours 10 PM - 7 AM', 1200.00, 'USD', 4, 80.00, TRUE, TRUE, TRUE, TRUE, FALSE, TRUE, 'Personal vehicles', '24/7', 'Mike Residential', '+1234567892', 'mike@company.com'),
(1, 'AREA004', 'Container Shop Zone', 'container', 'Specialized area for container-based businesses and shops', 'Dedicated container area with easy setup', 'Container-ready, Electricity, Water, Security, Parking, Loading area', 'Container businesses only, Proper permits required, Regular inspections', 1800.00, 'USD', 3, 150.00, TRUE, TRUE, TRUE, TRUE, TRUE, FALSE, 'Medium trucks', '7:00 AM - 9:00 PM', 'Lisa Container', '+1234567893', 'lisa@company.com'),
(1, 'AREA005', 'Event Space', 'event', 'Versatile event space for parties, meetings, and special occasions', 'Central location with good accessibility', 'Event lighting, Sound system, Catering kitchen, Parking for 50 vehicles, Stage area', 'Event bookings only, Noise restrictions, Clean-up required, Insurance mandatory', 3000.00, 'USD', 100, 300.00, TRUE, TRUE, TRUE, TRUE, FALSE, TRUE, 'Event vehicles', '6:00 AM - 12:00 AM', 'David Events', '+1234567894', 'david@company.com');

-- Update existing area_rentals with enhanced data
UPDATE area_rentals SET 
    rental_type = 'commercial',
    business_type = 'Retail Shop',
    expected_income = 5000.00,
    security_deposit = 1000.00,
    currency = 'USD',
    payment_frequency = 'monthly',
    auto_renewal = TRUE,
    insurance_required = TRUE,
    permit_required = TRUE
WHERE id = 1;

-- Insert sample area rental payments
INSERT INTO area_rental_payments (company_id, area_rental_id, payment_code, payment_date, amount, currency, payment_method, payment_type, reference_number, notes) VALUES
(1, 1, 'ARP001', CURDATE(), 2500.00, 'USD', 'bank_transfer', 'rent', 'TXN123456', 'Monthly rent payment'),
(1, 1, 'ARP002', CURDATE(), 1000.00, 'USD', 'cash', 'deposit', 'DEP789012', 'Security deposit payment');

-- Insert sample documents
INSERT INTO area_rental_documents (company_id, area_rental_id, document_type, document_name, file_path, file_size, mime_type) VALUES
(1, 1, 'contract', 'Rental Agreement.pdf', '/uploads/area_rentals/contract_1.pdf', 1024000, 'application/pdf'),
(1, 1, 'insurance', 'Insurance Certificate.pdf', '/uploads/area_rentals/insurance_1.pdf', 512000, 'application/pdf'),
(1, 1, 'permit', 'Business Permit.pdf', '/uploads/area_rentals/permit_1.pdf', 256000, 'application/pdf');

-- Insert sample maintenance records
INSERT INTO area_rental_maintenance (company_id, area_rental_id, maintenance_type, description, reported_date, scheduled_date, status, priority, assigned_to, notes) VALUES
(1, 1, 'electrical', 'Electrical outlet not working in corner area', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'scheduled', 'medium', 'Electrician Team', 'Client reported issue with power outlet'),
(1, 1, 'cleaning', 'Regular area cleaning and maintenance', CURDATE(), CURDATE(), 'completed', 'low', 'Cleaning Staff', 'Monthly cleaning service completed');

-- Insert sample visits
INSERT INTO area_rental_visits (company_id, area_rental_id, visit_date, visit_time, visitor_name, visitor_contact, purpose, duration_minutes, notes) VALUES
(1, 1, CURDATE(), '10:00:00', 'John Smith', '+1234567890', 'Area inspection and maintenance check', 45, 'Regular monthly inspection completed'),
(1, 1, DATE_SUB(CURDATE(), INTERVAL 7 DAY), '14:30:00', 'Sarah Johnson', '+1234567891', 'Client meeting and area discussion', 60, 'Discussed rental terms and area usage');