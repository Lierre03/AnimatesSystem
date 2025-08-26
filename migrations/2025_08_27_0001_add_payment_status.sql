-- Add payment status fields to bookings table
ALTER TABLE bookings 
ADD COLUMN payment_status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending' AFTER status,
ADD COLUMN payment_method VARCHAR(50) NULL AFTER payment_status,
ADD COLUMN payment_reference VARCHAR(100) NULL AFTER payment_method,
ADD COLUMN payment_platform VARCHAR(50) NULL AFTER payment_reference,
ADD COLUMN payment_date TIMESTAMP NULL AFTER payment_platform;

-- Add index for payment status queries
CREATE INDEX idx_bookings_payment_status ON bookings(payment_status);
CREATE INDEX idx_bookings_custom_rfid ON bookings(custom_rfid);
