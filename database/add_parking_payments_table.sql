-- Add Parking Payments Table
-- This table stores payment records for parking rentals

USE construction_saas;

-- Parking Payments Table
CREATE TABLE parking_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    rental_id INT NOT NULL,
    payment_code VARCHAR(20) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    payment_method VARCHAR(50) NOT NULL,
    payment_date DATE NOT NULL,
    reference_number VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (rental_id) REFERENCES parking_rentals(id) ON DELETE CASCADE,
    UNIQUE KEY unique_payment_code (payment_code)
);

-- Add indexes for better performance
CREATE INDEX idx_parking_payments_company_id ON parking_payments(company_id);
CREATE INDEX idx_parking_payments_rental_id ON parking_payments(rental_id);
CREATE INDEX idx_parking_payments_payment_date ON parking_payments(payment_date);

-- Insert sample payment data (optional)
INSERT INTO parking_payments (company_id, rental_id, payment_code, amount, currency, payment_method, payment_date, reference_number, notes) VALUES
(1, 1, 'PAY-001', 8000.00, 'USD', 'bank_transfer', '2023-06-01', 'TXN-001', 'June parking rental payment'),
(1, 1, 'PAY-002', 8000.00, 'USD', 'cash', '2023-07-01', 'CASH-001', 'July parking rental payment'),
(2, 2, 'PAY-003', 4500.00, 'USD', 'credit_card', '2023-06-01', 'CC-001', 'June parking rental payment'),
(2, 2, 'PAY-004', 4500.00, 'USD', 'bank_transfer', '2023-07-01', 'TXN-002', 'July parking rental payment');