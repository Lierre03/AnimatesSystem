-- Fix sales_transactions table to ensure discount_amount column exists
-- This migration ensures the discount system works properly

-- Check if sales_transactions table exists, if not create it
CREATE TABLE IF NOT EXISTS sales_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    transaction_reference VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    payment_platform VARCHAR(50),
    discount_amount DECIMAL(10,2) DEFAULT 0.00,
    status VARCHAR(20) DEFAULT 'completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id)
);

-- Add discount_amount column if it doesn't exist
ALTER TABLE sales_transactions 
ADD COLUMN IF NOT EXISTS discount_amount DECIMAL(10,2) DEFAULT 0.00;

-- Update existing records to have 0 discount if NULL
UPDATE sales_transactions 
SET discount_amount = 0.00 
WHERE discount_amount IS NULL;

-- Create index for better performance
CREATE INDEX IF NOT EXISTS idx_sales_transactions_booking_id ON sales_transactions(booking_id);
CREATE INDEX IF NOT EXISTS idx_sales_transactions_discount ON sales_transactions(discount_amount);

-- Verify the structure
DESCRIBE sales_transactions;
