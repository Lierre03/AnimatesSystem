-- Add discount_amount to bookings table
-- Add discount_amount to sales_transactions table only
ALTER TABLE sales_transactions
ADD COLUMN discount_amount DECIMAL(10, 2) DEFAULT 0.00;