-- =====================================================
-- MIGRATION: Remove 'pending' from payment status enums
-- Date: 2025-01-28
-- Description: Updates existing database to remove 'pending' payment status
-- =====================================================

USE `8paws_db`;

-- =====================================================
-- STEP 1: Update existing records to change 'pending' to 'paid'
-- =====================================================

-- Update bookings table - change 'pending' to 'paid'
UPDATE `bookings` 
SET `payment_status` = 'paid' 
WHERE `payment_status` = 'pending';

-- Update billing table - change 'pending' to 'paid'
UPDATE `billing` 
SET `payment_status` = 'paid' 
WHERE `payment_status` = 'pending';

-- =====================================================
-- STEP 2: Modify the enum constraints
-- =====================================================

-- Update bookings table enum
ALTER TABLE `bookings` 
MODIFY COLUMN `payment_status` enum('paid','refunded') NOT NULL DEFAULT 'paid';

-- Update billing table enum
ALTER TABLE `billing` 
MODIFY COLUMN `payment_status` enum('paid','overdue') NOT NULL DEFAULT 'paid';

-- =====================================================
-- STEP 3: Update any views that reference the old enum
-- =====================================================

-- Drop and recreate the active bookings view
DROP VIEW IF EXISTS `v_active_bookings`;

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

-- =====================================================
-- STEP 4: Verify the changes
-- =====================================================

-- Check current payment status values in bookings
SELECT 
    'bookings' as table_name,
    payment_status,
    COUNT(*) as count
FROM bookings 
GROUP BY payment_status;

-- Check current payment status values in billing
SELECT 
    'billing' as table_name,
    payment_status,
    COUNT(*) as count
FROM billing 
GROUP BY payment_status;

-- =====================================================
-- STEP 5: Update any stored procedures if they exist
-- =====================================================

-- Check if ProcessPayment procedure exists and update it
-- (This will only work if the procedure exists)
DELIMITER //

DROP PROCEDURE IF EXISTS `ProcessPayment`//

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
-- STEP 6: Update triggers if they exist
-- =====================================================

-- Drop and recreate the billing trigger
DROP TRIGGER IF EXISTS `tr_booking_billing`//

CREATE TRIGGER `tr_booking_billing` 
AFTER INSERT ON `bookings`
FOR EACH ROW
BEGIN
    INSERT INTO billing (booking_id, total_amount, discount_amount, final_amount, payment_status)
    VALUES (NEW.id, NEW.total_amount, 0.00, NEW.total_amount, 'paid');
END//

-- =====================================================
-- MIGRATION COMPLETE
-- =====================================================

SELECT 'Migration completed successfully!' as status;
SELECT 'Payment status enums updated: pending -> paid' as changes;
SELECT 'All existing pending records converted to paid' as data_update;
