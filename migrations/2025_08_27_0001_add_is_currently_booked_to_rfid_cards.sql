-- Add is_currently_booked column to rfid_cards table
ALTER TABLE `rfid_cards` ADD COLUMN `is_currently_booked` TINYINT(1) DEFAULT 0 COMMENT 'Indicates if the card is currently assigned to an active booking';

-- Update existing records based on active bookings
UPDATE `rfid_cards` rc
SET rc.`is_currently_booked` = 1
WHERE EXISTS (
    SELECT 1 FROM `bookings` b 
    WHERE (b.`rfid_card_id` = rc.`id` OR b.`custom_rfid` = rc.`custom_uid`)
    AND b.`status` NOT IN ('completed', 'cancelled')
);

-- Create trigger to automatically update is_currently_booked when a booking is created
DELIMITER //
CREATE TRIGGER `after_booking_insert` AFTER INSERT ON `bookings`
FOR EACH ROW
BEGIN
    IF NEW.`custom_rfid` IS NOT NULL THEN
        UPDATE `rfid_cards` 
        SET `is_currently_booked` = 1 
        WHERE `custom_uid` = NEW.`custom_rfid`;
    END IF;
    
    IF NEW.`rfid_card_id` IS NOT NULL THEN
        UPDATE `rfid_cards` 
        SET `is_currently_booked` = 1 
        WHERE `id` = NEW.`rfid_card_id`;
    END IF;
END //
DELIMITER ;

-- Create trigger to automatically update is_currently_booked when a booking is updated
DELIMITER //
CREATE TRIGGER `after_booking_update` AFTER UPDATE ON `bookings`
FOR EACH ROW
BEGIN
    -- If status changed to completed or cancelled, update the card
    IF NEW.`status` IN ('completed', 'cancelled') AND OLD.`status` NOT IN ('completed', 'cancelled') THEN
        -- Update by custom_rfid
        IF NEW.`custom_rfid` IS NOT NULL THEN
            UPDATE `rfid_cards` 
            SET `is_currently_booked` = 0 
            WHERE `custom_uid` = NEW.`custom_rfid`
            AND NOT EXISTS (
                SELECT 1 FROM `bookings` 
                WHERE `custom_rfid` = NEW.`custom_rfid`
                AND `id` != NEW.`id`
                AND `status` NOT IN ('completed', 'cancelled')
            );
        END IF;
        
        -- Update by rfid_card_id
        IF NEW.`rfid_card_id` IS NOT NULL THEN
            UPDATE `rfid_cards` 
            SET `is_currently_booked` = 0 
            WHERE `id` = NEW.`rfid_card_id`
            AND NOT EXISTS (
                SELECT 1 FROM `bookings` 
                WHERE `rfid_card_id` = NEW.`rfid_card_id`
                AND `id` != NEW.`id`
                AND `status` NOT IN ('completed', 'cancelled')
            );
        END IF;
    END IF;
END //
DELIMITER ;

-- Create trigger to handle booking deletion
DELIMITER //
CREATE TRIGGER `after_booking_delete` AFTER DELETE ON `bookings`
FOR EACH ROW
BEGIN
    -- Only process if the booking was active
    IF OLD.`status` NOT IN ('completed', 'cancelled') THEN
        -- Update by custom_rfid
        IF OLD.`custom_rfid` IS NOT NULL THEN
            UPDATE `rfid_cards` 
            SET `is_currently_booked` = 0 
            WHERE `custom_uid` = OLD.`custom_rfid`
            AND NOT EXISTS (
                SELECT 1 FROM `bookings` 
                WHERE `custom_rfid` = OLD.`custom_rfid`
                AND `id` != OLD.`id`
                AND `status` NOT IN ('completed', 'cancelled')
            );
        END IF;
        
        -- Update by rfid_card_id
        IF OLD.`rfid_card_id` IS NOT NULL THEN
            UPDATE `rfid_cards` 
            SET `is_currently_booked` = 0 
            WHERE `id` = OLD.`rfid_card_id`
            AND NOT EXISTS (
                SELECT 1 FROM `bookings` 
                WHERE `rfid_card_id` = OLD.`rfid_card_id`
                AND `id` != OLD.`id`
                AND `status` NOT IN ('completed', 'cancelled')
            );
        END IF;
    END IF;
END //
DELIMITER ;