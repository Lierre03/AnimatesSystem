# Database Relationships Guide

## Overview
This document shows the relationships between bookings, RFID cards, pets, and customers in the 8paws_db database.

## Table Relationships

### Primary Tables and Their Relationships

```
CUSTOMERS (1) ←→ (Many) PETS (1) ←→ (Many) BOOKINGS (1) ←→ (1) RFID_CARDS
```

### Detailed Relationship Flow

1. **CUSTOMERS** (Parent Table)
   - Primary Key: `id`
   - Contains: customer information (name, phone, email, address)

2. **PETS** (Child of CUSTOMERS)
   - Primary Key: `id`
   - Foreign Key: `customer_id` → references `customers.id`
   - Contains: pet information (name, type, breed, size)

3. **BOOKINGS** (Child of PETS)
   - Primary Key: `id`
   - Foreign Key: `pet_id` → references `pets.id`
   - Contains: `custom_rfid` field (direct RFID assignment)
   - Contains: booking details (total_amount, status, payment_status, etc.)

4. **RFID_CARDS** (Related to BOOKINGS)
   - Primary Key: `id`
   - Contains: RFID card information
   - Connected via `custom_rfid` field in bookings table

## How to Navigate Relationships in phpMyAdmin

### Method 1: Using the View (Recommended)
1. Open phpMyAdmin
2. Select `8paws_db` database
3. Go to "Views" tab
4. Click on `booking_customer_rfid_view`
5. This shows all relationships in one table including:
   - Booking details (ID, RFID, total amount, status)
   - Pet information (name, type, breed)
   - Customer information (name, phone, email)
   - **Services associated with each booking**

### Method 2: Using Foreign Key Navigation
1. Open phpMyAdmin
2. Select `8paws_db` database
3. Click on `bookings` table
4. Look for the "Browse" tab
5. Click on any booking record
6. Look for foreign key links (usually highlighted or have special icons)
7. Click on the foreign key to navigate to related records

### Method 3: Manual JOIN Queries
Use these queries to explore relationships:

```sql
-- View all bookings with customer and pet info
SELECT 
    b.id as booking_id,
    b.custom_rfid,
    b.total_amount,
    b.status,
    p.name as pet_name,
    p.type as pet_type,
    c.name as customer_name,
    c.phone as customer_phone
FROM bookings b
LEFT JOIN pets p ON b.pet_id = p.id
LEFT JOIN customers c ON p.customer_id = c.id
ORDER BY b.check_in_time DESC;

-- Find all bookings for a specific customer
SELECT 
    b.id as booking_id,
    b.custom_rfid,
    b.total_amount,
    p.name as pet_name
FROM bookings b
LEFT JOIN pets p ON b.pet_id = p.id
LEFT JOIN customers c ON p.customer_id = c.id
WHERE c.name = 'Ivy Rivera';

-- Find all pets and their bookings for a customer
SELECT 
    p.name as pet_name,
    p.type as pet_type,
    COUNT(b.id) as total_bookings,
    SUM(b.total_amount) as total_spent
FROM pets p
LEFT JOIN bookings b ON p.id = b.pet_id
LEFT JOIN customers c ON p.customer_id = c.id
WHERE c.name = 'Ivy Rivera'
GROUP BY p.id;
```

## Current Database Structure

### Key Foreign Key Relationships
- `pets.customer_id` → `customers.id`
- `bookings.pet_id` → `pets.id`
- `booking_services.booking_id` → `bookings.id`
- `booking_services.service_id` → `services.id`

### RFID Assignment
- RFID cards are assigned directly to bookings via the `custom_rfid` field
- Each booking can have one RFID card
- RFID cards are unique per booking

## Sample Data Relationships

Based on current data:
- **Customer**: Ivy Rivera (ID: 43)
  - **Pet**: Test 1 (ID: 125) - Cat
    - **Booking**: RUFD7UUD (ID: 105) - ₱200.00 - Completed
      - **Services**: Ear Cleaning
  - **Pet**: Hotspot (ID: 124) - Dog
    - **Booking**: 4BJPCECB (ID: 103) - ₱500.00 - Completed
      - **Services**: Full Grooming Package
    - **Booking**: 1BL89OOX (ID: 104) - ₱280.00 - Completed
      - **Services**: Dental Care
- **Customer**: Shiva Natal
  - **Pet**: Buddy
    - **Booking**: 331ADO13 (ID: 102) - ₱1050.00 - Completed
      - **Services**: Full Grooming Package, Perfume & Bow

## Navigation Tips in phpMyAdmin

1. **To see all bookings for a customer:**
   - Go to `customers` table
   - Click on customer name
   - Look for related records or use "Browse" tab

2. **To see all pets for a customer:**
   - Go to `pets` table
   - Filter by `customer_id`

3. **To see all bookings for a pet:**
   - Go to `bookings` table
   - Filter by `pet_id`

4. **To find RFID assignments:**
   - Go to `bookings` table
   - Look for `custom_rfid` field
   - Non-null values indicate RFID assignments

## Quick Reference Commands

```sql
-- Show all relationships for a specific RFID (including services)
SELECT * FROM booking_customer_rfid_view WHERE custom_rfid = 'RUFD7UUD';

-- Show all customers with their total bookings and services
SELECT 
    c.name,
    COUNT(b.id) as total_bookings,
    SUM(b.total_amount) as total_spent,
    GROUP_CONCAT(DISTINCT s.name SEPARATOR ', ') as all_services_used
FROM customers c
LEFT JOIN pets p ON c.id = p.customer_id
LEFT JOIN bookings b ON p.id = b.pet_id
LEFT JOIN booking_services bs ON b.id = bs.booking_id
LEFT JOIN services s ON bs.service_id = s.id
GROUP BY c.id
ORDER BY total_spent DESC;

-- Show all RFID assignments with services
SELECT 
    b.custom_rfid,
    p.name as pet_name,
    c.name as customer_name,
    b.total_amount,
    b.status,
    GROUP_CONCAT(s.name SEPARATOR ', ') as services
FROM bookings b
LEFT JOIN pets p ON b.pet_id = p.id
LEFT JOIN customers c ON p.customer_id = c.id
LEFT JOIN booking_services bs ON b.id = bs.booking_id
LEFT JOIN services s ON bs.service_id = s.id
WHERE b.custom_rfid IS NOT NULL
GROUP BY b.id
ORDER BY b.check_in_time DESC;

-- Show all services for a specific booking
SELECT 
    b.id as booking_id,
    b.custom_rfid,
    s.name as service_name,
    s.price as service_price,
    bs.price as actual_price,
    bs.pet_size
FROM bookings b
LEFT JOIN booking_services bs ON b.id = bs.booking_id
LEFT JOIN services s ON bs.service_id = s.id
WHERE b.custom_rfid = 'RUFD7UUD';
```

This structure allows you to easily trace from any RFID card back to the customer, or from any customer to all their pets and bookings.
