-- Add payment amount fields to bookings table
ALTER TABLE bookings 
ADD COLUMN amount_tendered DECIMAL(10,2) NULL AFTER payment_platform,
ADD COLUMN change_amount DECIMAL(10,2) NULL AFTER amount_tendered;



