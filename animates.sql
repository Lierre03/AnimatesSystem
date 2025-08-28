-- =====================================================
-- ANIMATES PH DATABASE STRUCTURE - RESET VERSION
-- =====================================================
-- This file contains the complete database structure for Animates PH
-- It's designed as a reset version with no data except essential admin/staff accounts
-- Generated on: 2025-01-28
-- Database: 8paws_db
-- =====================================================

-- Drop database if exists and create new one
DROP DATABASE IF EXISTS `8paws_db`;
CREATE DATABASE `8paws_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `8paws_db`;

-- =====================================================
-- USERS TABLE (Admin and Staff Accounts)
-- =====================================================
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL UNIQUE,
  `full_name` varchar(100) NOT NULL,
  `role` enum('admin','staff','cashier') NOT NULL DEFAULT 'staff',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_username` (`username`),
  KEY `idx_email` (`email`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert essential admin and staff accounts
INSERT INTO `users` (`username`, `password`, `email`, `full_name`, `role`, `is_active`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@animates.ph', 'System Administrator', 'admin', 1),
('staff1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff1@animates.ph', 'Staff Member 1', 'staff', 1),
('cashier1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cashier1@animates.ph', 'Cashier 1', 'cashier', 1);

-- =====================================================
-- CUSTOMERS TABLE
-- =====================================================
CREATE TABLE `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`),
  KEY `idx_phone` (`phone`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- PETS TABLE
-- =====================================================
CREATE TABLE `pets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('dog','cat','bird','rabbit','other') NOT NULL DEFAULT 'dog',
  `breed` varchar(100) DEFAULT NULL,
  `size` enum('small','medium','large','xlarge') NOT NULL DEFAULT 'medium',
  `age` int(11) DEFAULT NULL,
  `gender` enum('male','female') DEFAULT NULL,
  `special_notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_type` (`type`),
  KEY `idx_size` (`size`),
  CONSTRAINT `fk_pets_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SERVICES TABLE
-- =====================================================
CREATE TABLE `services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `price` decimal(10,2) NOT NULL,
  `duration_minutes` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`),
  KEY `idx_price` (`price`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default services
INSERT INTO `services` (`name`, `description`, `price`, `duration_minutes`, `is_active`) VALUES
('Basic Bath', 'Basic pet bathing service', 300.00, 30, 1),
('Full Grooming', 'Complete pet grooming service', 500.00, 60, 1),
('Nail Trimming', 'Pet nail trimming service', 100.00, 15, 1),
('Cat Bath', 'Cat bathing service', 250.00, 25, 1),
('Cat Grooming', 'Cat grooming service', 400.00, 45, 1),
('Ear Cleaning', 'Pet ear cleaning service', 150.00, 20, 1);

-- =====================================================
-- RFID_CARDS TABLE
-- =====================================================
CREATE TABLE `rfid_cards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `card_number` varchar(50) NOT NULL UNIQUE,
  `custom_rfid` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_currently_booked` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_card_number` (`card_number`),
  KEY `idx_custom_rfid` (`custom_rfid`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_is_currently_booked` (`is_currently_booked`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- BOOKINGS TABLE
-- =====================================================
CREATE TABLE `bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pet_id` int(11) NOT NULL,
  `custom_rfid` varchar(50) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `payment_status` enum('paid','refunded') NOT NULL DEFAULT 'paid',
  `check_in_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `estimated_completion` timestamp NULL DEFAULT NULL,
  `actual_completion` timestamp NULL DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `payment_platform` varchar(50) DEFAULT NULL,
  `amount_tendered` decimal(10,2) DEFAULT NULL,
  `change_amount` decimal(10,2) DEFAULT NULL,
  `payment_date` timestamp NULL DEFAULT NULL,
  `staff_notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pet_id` (`pet_id`),
  KEY `idx_custom_rfid` (`custom_rfid`),
  KEY `idx_status` (`status`),
  KEY `idx_payment_status` (`payment_status`),
  KEY `idx_check_in_time` (`check_in_time`),
  CONSTRAINT `fk_bookings_pet` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- BOOKING_SERVICES TABLE
-- =====================================================
CREATE TABLE `booking_services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_booking_id` (`booking_id`),
  KEY `idx_service_id` (`service_id`),
  CONSTRAINT `fk_booking_services_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_booking_services_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SALES_TRANSACTIONS TABLE
-- =====================================================
CREATE TABLE `sales_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `transaction_reference` varchar(100) NOT NULL UNIQUE,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `payment_platform` varchar(50) DEFAULT NULL,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','completed','voided','refunded') NOT NULL DEFAULT 'completed',
  `void_reason` text,
  `voided_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_booking_id` (`booking_id`),
  KEY `idx_transaction_reference` (`transaction_reference`),
  KEY `idx_payment_method` (`payment_method`),
  KEY `idx_status` (`status`),
  KEY `idx_discount_amount` (`discount_amount`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_sales_transactions_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- VOID_AUDIT_LOG TABLE
-- =====================================================
CREATE TABLE `void_audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_id` int(11) NOT NULL,
  `void_reason` text NOT NULL,
  `voided_by` int(11) NOT NULL,
  `voided_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_transaction_id` (`transaction_id`),
  KEY `idx_voided_by` (`voided_by`),
  KEY `idx_voided_at` (`voided_at`),
  CONSTRAINT `fk_void_audit_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `sales_transactions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_void_audit_user` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- APPOINTMENTS TABLE
-- =====================================================
CREATE TABLE `appointments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pet_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `service_type` varchar(100) NOT NULL,
  `notes` text,
  `status` enum('scheduled','confirmed','completed','cancelled','no_show') NOT NULL DEFAULT 'scheduled',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pet_id` (`pet_id`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_appointment_date` (`appointment_date`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_appointments_pet` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_appointments_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TRACKING TABLE
-- =====================================================
CREATE TABLE `tracking` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `status` varchar(50) NOT NULL,
  `notes` text,
  `tracked_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_booking_id` (`booking_id`),
  KEY `idx_status` (`status`),
  KEY `idx_tracked_by` (`tracked_by`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_tracking_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tracking_user` FOREIGN KEY (`tracked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- CHECK_IN TABLE
-- =====================================================
CREATE TABLE `check_in` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `check_in_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `check_in_by` int(11) DEFAULT NULL,
  `notes` text,
  PRIMARY KEY (`id`),
  KEY `idx_booking_id` (`booking_id`),
  KEY `idx_check_in_time` (`check_in_time`),
  KEY `idx_check_in_by` (`check_in_by`),
  CONSTRAINT `fk_check_in_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_check_in_user` FOREIGN KEY (`check_in_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- BILLING TABLE
-- =====================================================
CREATE TABLE `billing` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `final_amount` decimal(10,2) NOT NULL,
  `payment_status` enum('paid','overdue') NOT NULL DEFAULT 'paid',
  `due_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_booking_id` (`booking_id`),
  KEY `idx_payment_status` (`payment_status`),
  KEY `idx_due_date` (`due_date`),
  CONSTRAINT `fk_billing_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- CREATE INDEXES FOR PERFORMANCE
-- =====================================================

-- Composite indexes for better query performance
CREATE INDEX `idx_bookings_status_payment` ON `bookings` (`status`, `payment_status`);
CREATE INDEX `idx_bookings_rfid_status` ON `bookings` (`custom_rfid`, `status`);
CREATE INDEX `idx_sales_transactions_booking_status` ON `sales_transactions` (`booking_id`, `status`);
CREATE INDEX `idx_pets_customer_type` ON `pets` (`customer_id`, `type`);
CREATE INDEX `idx_appointments_date_status` ON `appointments` (`appointment_date`, `status`);

-- =====================================================
-- INSERT SAMPLE DATA FOR TESTING (OPTIONAL)
-- =====================================================

-- Insert sample customers for testing
INSERT INTO `customers` (`name`, `phone`, `email`, `address`) VALUES
('John Doe', '+639123456789', 'john.doe@email.com', '123 Main St, City'),
('Jane Smith', '+639987654321', 'jane.smith@email.com', '456 Oak Ave, Town'),
('Mike Johnson', '+639555123456', 'mike.johnson@email.com', '789 Pine Rd, Village');

-- Insert sample pets for testing
INSERT INTO `pets` (`customer_id`, `name`, `type`, `breed`, `size`, `age`, `gender`, `special_notes`) VALUES
(1, 'Buddy', 'dog', 'Golden Retriever', 'large', 3, 'male', 'Friendly and energetic'),
(1, 'Fluffy', 'cat', 'Persian', 'medium', 2, 'female', 'Loves to be brushed'),
(2, 'Max', 'dog', 'German Shepherd', 'large', 4, 'male', 'Very protective'),
(3, 'Whiskers', 'cat', 'Siamese', 'medium', 1, 'male', 'Playful and curious');

-- Insert sample RFID cards for testing
INSERT INTO `rfid_cards` (`card_number`, `custom_rfid`, `is_active`) VALUES
('RFID001', 'TEST001', 1),
('RFID002', 'TEST002', 1),
('RFID003', 'TEST003', 1),
('RFID004', 'TEST004', 1);

-- =====================================================
-- VIEWS FOR COMMON QUERIES
-- =====================================================

-- View for active bookings with customer and pet info
CREATE VIEW `v_active_bookings` AS
SELECT 
    b.id,
    b.custom_rfid,
    b.total_amount,
    b.status,
    b.payment_status,
    b.check_in_time,
    b.estimated_completion,
    c.name as customer_name,
    c.phone as customer_phone,
    c.email as customer_email,
    p.name as pet_name,
    p.type as pet_type,
    p.breed as pet_breed
FROM bookings b
JOIN pets p ON b.pet_id = p.id
JOIN customers c ON p.customer_id = c.id
WHERE b.status IN ('pending', 'in_progress')
ORDER BY b.check_in_time DESC;

-- View for completed transactions with discount info
CREATE VIEW `v_completed_transactions` AS
SELECT 
    st.id,
    st.transaction_reference,
    st.amount,
    st.discount_amount,
    st.payment_method,
    st.created_at,
    b.custom_rfid,
    c.name as customer_name,
    p.name as pet_name
FROM sales_transactions st
JOIN bookings b ON st.booking_id = b.id
JOIN pets p ON b.pet_id = p.id
JOIN customers c ON p.customer_id = c.id
WHERE st.status = 'completed'
ORDER BY st.created_at DESC;

-- =====================================================
-- STORED PROCEDURES FOR COMMON OPERATIONS
-- =====================================================

DELIMITER //

-- Procedure to create a new booking with services
CREATE PROCEDURE `CreateBooking`(
    IN p_pet_id INT,
    IN p_custom_rfid VARCHAR(50),
    IN p_total_amount DECIMAL(10,2),
    IN p_service_ids TEXT
)
BEGIN
    DECLARE v_booking_id INT;
    DECLARE v_service_id INT;
    DECLARE v_done INT DEFAULT FALSE;
    DECLARE v_cursor CURSOR FOR 
        SELECT CAST(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(p_service_ids, ',', numbers.n), ',', -1)) AS UNSIGNED) as service_id
        FROM (SELECT 1 n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5) numbers
        WHERE numbers.n <= 1 + (LENGTH(p_service_ids) - LENGTH(REPLACE(p_service_ids, ',', '')));
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = TRUE;
    
    -- Create the booking
    INSERT INTO bookings (pet_id, custom_rfid, total_amount, status) 
    VALUES (p_pet_id, p_custom_rfid, p_total_amount, 'pending');
    
    SET v_booking_id = LAST_INSERT_ID();
    
    -- Add services to the booking
    OPEN v_cursor;
    read_loop: LOOP
        FETCH v_cursor INTO v_service_id;
        IF v_done THEN
            LEAVE read_loop;
        END IF;
        
        INSERT INTO booking_services (booking_id, service_id, price)
        SELECT v_booking_id, id, price FROM services WHERE id = v_service_id;
    END LOOP;
    CLOSE v_cursor;
    
    SELECT v_booking_id as booking_id;
END //

-- Procedure to process payment with discount
CREATE PROCEDURE `ProcessPayment`(
    IN p_booking_id INT,
    IN p_payment_method VARCHAR(50),
    IN p_discount_amount DECIMAL(10,2),
    IN p_amount_tendered DECIMAL(10,2),
    IN p_change_amount DECIMAL(10,2)
)
BEGIN
    DECLARE v_total_amount DECIMAL(10,2);
    DECLARE v_final_amount DECIMAL(10,2);
    DECLARE v_transaction_ref VARCHAR(100);
    
    -- Get the total amount
    SELECT total_amount INTO v_total_amount FROM bookings WHERE id = p_booking_id;
    
    -- Calculate final amount after discount
    SET v_final_amount = v_total_amount - p_discount_amount;
    
    -- Generate transaction reference
    SET v_transaction_ref = CONCAT('TXN-', DATE_FORMAT(NOW(), '%Y%m%d'), '-', UPPER(SUBSTRING(MD5(RAND()), 1, 8)));
    
    -- Update booking status
    UPDATE bookings SET 
        status = 'completed',
        payment_status = 'paid',
        actual_completion = NOW(),
        payment_method = p_payment_method,
        amount_tendered = p_amount_tendered,
        change_amount = p_change_amount,
        payment_date = NOW()
    WHERE id = p_booking_id;
    
    -- Create sales transaction
    INSERT INTO sales_transactions (
        booking_id, 
        transaction_reference, 
        amount, 
        payment_method, 
        discount_amount, 
        status
    ) VALUES (
        p_booking_id, 
        v_transaction_ref, 
        v_final_amount, 
        p_payment_method, 
        p_discount_amount, 
        'completed'
    );
    
    SELECT v_transaction_ref as transaction_reference, v_final_amount as final_amount;
END //

DELIMITER ;

-- =====================================================
-- TRIGGERS FOR DATA INTEGRITY
-- =====================================================

DELIMITER //

-- Trigger to update RFID card status when booking is created
CREATE TRIGGER `tr_booking_created` 
AFTER INSERT ON `bookings`
FOR EACH ROW
BEGIN
    UPDATE rfid_cards 
    SET is_currently_booked = 1 
    WHERE custom_rfid = NEW.custom_rfid;
END //

-- Trigger to update RFID card status when booking is completed
CREATE TRIGGER `tr_booking_completed` 
AFTER UPDATE ON `bookings`
FOR EACH ROW
BEGIN
    IF NEW.status = 'completed' AND OLD.status != 'completed' THEN
        UPDATE rfid_cards 
        SET is_currently_booked = 0 
        WHERE custom_rfid = NEW.custom_rfid;
    END IF;
END //

-- Trigger to update billing when booking is created
CREATE TRIGGER `tr_booking_billing` 
AFTER INSERT ON `bookings`
FOR EACH ROW
BEGIN
    INSERT INTO billing (booking_id, total_amount, discount_amount, final_amount, payment_status)
    VALUES (NEW.id, NEW.total_amount, 0.00, NEW.total_amount, 'paid');
END //

DELIMITER ;

-- =====================================================
-- FINAL COMMENTS
-- =====================================================
-- 
-- This database structure includes:
-- 1. All necessary tables for the Animates PH system
-- 2. Proper foreign key constraints and relationships
-- 3. Essential admin/staff accounts (password: 'password')
-- 4. Sample data for testing
-- 5. Performance indexes and views
-- 6. Stored procedures for common operations
-- 7. Triggers for data integrity
-- 
-- To use this file:
-- 1. Import it into your MySQL/MariaDB server
-- 2. The database '8paws_db' will be created automatically
-- 3. All tables will be empty except for users and sample data
-- 4. Default admin account: username: 'admin', password: 'password'
-- 
-- Remember to change the default passwords in production!
-- =====================================================
