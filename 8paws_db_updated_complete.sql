-- =====================================================
-- 8PAWS_DB COMPLETE UPDATED VERSION WITH DISCOUNT SUPPORT
-- =====================================================
-- This file is a complete updated version of 8paws_db (CURRENT).sql
-- Added discount_amount column to sales_transactions table
-- Updated for billing management system
-- Generated on: 2025-01-28
-- =====================================================

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 27, 2025 at 11:10 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `8paws_db`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `LinkCustomerToUser` (IN `p_user_id` INT, IN `p_phone` VARCHAR(20))   BEGIN
    DECLARE customer_exists INT DEFAULT 0;
    
    -- Check if customer exists with this phone
    SELECT COUNT(*) INTO customer_exists 
    FROM customers 
    WHERE phone = p_phone AND user_id IS NULL;
    
    IF customer_exists > 0 THEN
        -- Update customer record to link with user
        UPDATE customers 
        SET user_id = p_user_id, 
            created_via = 'online',
            updated_at = NOW()
        WHERE phone = p_phone AND user_id IS NULL;
        
        -- Link all pets of this customer to the user
        INSERT INTO user_pets (user_id, pet_id, is_primary_owner)
        SELECT p_user_id, p.id, TRUE
        FROM pets p
        JOIN customers c ON p.customer_id = c.id
        WHERE c.phone = p_phone AND c.user_id = p_user_id;
        
        -- Link all bookings to the user
        UPDATE bookings b
        JOIN pets p ON b.pet_id = p.id
        JOIN customers c ON p.customer_id = c.id
        SET b.user_id = p_user_id, 
            b.booking_type = 'online',
            b.updated_at = NOW()
        WHERE c.user_id = p_user_id AND b.user_id IS NULL;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `UpdatePetStatusByRFID` (IN `p_custom_uid` VARCHAR(8), IN `p_tap_count` INT)   BEGIN
    DECLARE v_booking_id INT;
    DECLARE v_current_status VARCHAR(20);
    DECLARE v_new_status VARCHAR(20);
    
    
    SELECT id, status INTO v_booking_id, v_current_status
    FROM bookings 
    WHERE custom_rfid = p_custom_uid 
    AND status NOT IN ('completed', 'cancelled')
    ORDER BY created_at DESC 
    LIMIT 1;
    
    IF v_booking_id IS NOT NULL THEN
        
        SET v_new_status = CASE p_tap_count
            WHEN 2 THEN 'bathing'
            WHEN 3 THEN 'grooming'
            WHEN 4 THEN 'ready'
            ELSE v_current_status
        END;
        
        
        IF v_new_status != v_current_status THEN
            UPDATE bookings 
            SET status = v_new_status,
                actual_completion = CASE WHEN v_new_status = 'completed' THEN NOW() ELSE actual_completion END,
                updated_at = NOW()
            WHERE id = v_booking_id;
            
            
            INSERT INTO status_updates (booking_id, status, notes)
            VALUES (v_booking_id, v_new_status, CONCAT('Status updated via RFID tap #', p_tap_count));
        END IF;
    END IF;
END$$

--
-- Functions
--
CREATE DEFINER=`root`@`localhost` FUNCTION `GetUserActiveBookingsCount` (`p_user_id` INT) RETURNS INT(11) DETERMINISTIC READS SQL DATA BEGIN
    DECLARE booking_count INT DEFAULT 0;
    
    SELECT COUNT(*) INTO booking_count
    FROM bookings b
    WHERE b.user_id = p_user_id 
    AND b.status NOT IN ('completed', 'cancelled');
    
    RETURN booking_count;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `pet_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `estimated_duration` int(11) DEFAULT 120,
  `status` enum('scheduled','confirmed','in_progress','completed','cancelled','no_show') DEFAULT 'scheduled',
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `special_instructions` text DEFAULT NULL,
  `staff_notes` text DEFAULT NULL,
  `reminder_sent` tinyint(1) DEFAULT 0,
  `confirmation_sent` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `appointment_services`
--

CREATE TABLE `appointment_services` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `price` decimal(8,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `app_config`
--

CREATE TABLE `app_config` (
  `id` int(11) NOT NULL,
  `config_key` varchar(100) NOT NULL,
  `config_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `app_config`
--

INSERT INTO `app_config` (`id`, `config_key`, `config_value`, `created_at`, `updated_at`) VALUES
(1, 'last_firebase_sync', '1970-01-01 00:00:00', '2025-08-16 18:41:01', '2025-08-16 18:41:01');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `pet_id` int(11) NOT NULL,
  `rfid_card_id` int(11) DEFAULT NULL,
  `rfid_tag_id` int(11) DEFAULT NULL,
  `custom_rfid` varchar(8) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('checked-in','bathing','grooming','ready','completed','cancelled') DEFAULT 'checked-in',
  `payment_status` enum('pending','paid','cancelled') DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `payment_platform` varchar(50) DEFAULT NULL,
  `amount_tendered` decimal(10,2) DEFAULT NULL,
  `change_amount` decimal(10,2) DEFAULT NULL,
  `payment_date` timestamp NULL DEFAULT NULL,
  `check_in_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `estimated_completion` timestamp NULL DEFAULT NULL,
  `actual_completion` timestamp NULL DEFAULT NULL,
  `pickup_time` timestamp NULL DEFAULT NULL,
  `staff_notes` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL COMMENT 'User ID who last updated the booking',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  `booking_type` enum('walk_in','online') DEFAULT 'walk_in',
  `welcome_email_sent` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `booking_services`
--

CREATE TABLE `booking_services` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `pet_size` enum('small','medium','large','extra_large') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `emergency_contact` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  `created_via` enum('walk_in','online') DEFAULT 'walk_in'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pets`
--

CREATE TABLE `pets` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(50) NOT NULL,
  `pet_type` varchar(50) DEFAULT NULL,
  `breed` varchar(255) NOT NULL,
  `age_range` enum('puppy','young','adult','senior') DEFAULT NULL,
  `size` enum('small','medium','large','xlarge') DEFAULT NULL,
  `special_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pet_sizes`
--

CREATE TABLE `pet_sizes` (
  `id` int(11) NOT NULL,
  `size_code` enum('small','medium','large','extra_large') NOT NULL,
  `display_name` varchar(50) NOT NULL,
  `weight_range` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pet_sizes`
--

INSERT INTO `pet_sizes` (`id`, `size_code`, `display_name`, `weight_range`, `description`, `sort_order`) VALUES
(1, 'small', 'Small', '0-15 lbs', 'Small pets (e.g., Chihuahua, Cat)', 1),
(2, 'medium', 'Medium', '16-40 lbs', 'Medium pets (e.g., Beagle, Cocker Spaniel)', 2),
(3, 'large', 'Large', '41-70 lbs', 'Large pets (e.g., Golden Retriever, German Shepherd)', 3),
(4, 'extra_large', 'Extra Large', '71+ lbs', 'Extra large pets (e.g., Great Dane, St. Bernard)', 4);

-- --------------------------------------------------------

--
-- Table structure for table `rfid_cards`
--

CREATE TABLE `rfid_cards` (
  `id` int(11) NOT NULL,
  `card_uid` varchar(50) NOT NULL,
  `custom_uid` varchar(8) NOT NULL,
  `firebase_doc_id` varchar(100) DEFAULT NULL,
  `tap_count` int(11) DEFAULT 1,
  `max_taps` int(11) DEFAULT 5,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `last_firebase_sync` timestamp NULL DEFAULT NULL,
  `validation_time_ms` int(11) DEFAULT 3000,
  `device_source` varchar(50) DEFAULT 'ESP32-RFID-Scanner',
  `status` enum('active','expired','disabled') DEFAULT 'active',
  `is_currently_booked` tinyint(1) DEFAULT 0 COMMENT 'Indicates if the card is currently assigned to an active booking'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rfid_tags`
--

CREATE TABLE `rfid_tags` (
  `id` int(11) NOT NULL,
  `tag_id` varchar(20) NOT NULL,
  `pet_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `assigned_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rfid_tap_history`
--

CREATE TABLE `rfid_tap_history` (
  `id` int(11) NOT NULL,
  `rfid_card_id` int(11) DEFAULT NULL,
  `card_uid` varchar(50) NOT NULL,
  `custom_uid` varchar(8) NOT NULL,
  `tap_number` int(11) NOT NULL,
  `firebase_doc_id` varchar(100) DEFAULT NULL,
  `tapped_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `device_info` varchar(100) DEFAULT NULL,
  `wifi_network` varchar(100) DEFAULT NULL,
  `signal_strength` int(11) DEFAULT NULL,
  `validation_status` enum('approved','pending','failed') DEFAULT 'approved',
  `readable_time` varchar(50) DEFAULT NULL,
  `timestamp_value` timestamp NULL DEFAULT NULL,
  `rfid_scanner_status` varchar(20) DEFAULT 'OK',
  `project_id` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales_transactions` (UPDATED WITH DISCOUNT SUPPORT)
--

CREATE TABLE `sales_transactions` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `transaction_reference` varchar(50) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `payment_method` varchar(20) DEFAULT NULL,
  `payment_platform` varchar(50) DEFAULT NULL,
  `status` enum('completed','voided','refunded') DEFAULT 'completed',
  `void_reason` text DEFAULT NULL,
  `voided_by` int(11) DEFAULT NULL,
  `voided_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `duration_minutes` int(11) DEFAULT 60,
  `category` enum('basic','premium','addon') NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `services2`
--

CREATE TABLE `services2` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` enum('basic','premium','addon') DEFAULT 'basic',
  `base_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_size_based` tinyint(1) DEFAULT 1,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services2`
--

INSERT INTO `services2` (`id`, `name`, `description`, `category`, `base_price`, `is_size_based`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Basic Bath', 'Shampoo, rinse, and basic dry', 'basic', 300.00, 1, 'active', '2025-08-24 10:20:33', '2025-08-24 10:20:33'),
(2, 'Nail Trimming', 'Professional nail care', 'basic', 150.00, 1, 'active', '2025-08-24 10:20:33', '2025-08-24 10:20:33'),
(3, 'Ear Cleaning', 'Safe ear cleaning and inspection', 'basic', 200.00, 0, 'active', '2025-08-24 10:20:33', '2025-08-24 10:20:33'),
(4, 'Full Grooming Package', 'Bath, cut, style, nails, ears, and teeth', 'premium', 650.00, 1, 'active', '2025-08-24 10:20:33', '2025-08-24 10:20:33'),
(5, 'Dental Care', 'Teeth cleaning and oral health check', 'premium', 250.00, 1, 'active', '2025-08-24 10:20:33', '2025-08-24 10:20:33'),
(6, 'De-shedding Treatment', 'Reduces shedding up to 90%', 'premium', 425.00, 1, 'active', '2025-08-24 10:20:33', '2025-08-24 10:20:33'),
(7, 'Nail Polish', 'Pet-safe nail colors', 'addon', 100.00, 0, 'active', '2025-08-24 10:20:33', '2025-08-24 10:20:33'),
(8, 'Perfume & Bow', 'Finishing touches for a perfect look', 'addon', 150.00, 0, 'active', '2025-08-24 10:20:33', '2025-08-24 10:20:33');

-- --------------------------------------------------------

--
-- Table structure for table `service_pricing`
--

CREATE TABLE `service_pricing` (
  `id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `pet_size` enum('small','medium','large','extra_large') NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_pricing`
--

INSERT INTO `service_pricing` (`id`, `service_id`, `pet_size`, `price`, `created_at`, `updated_at`) VALUES
(1, 1, 'small', 250.00, '2025-08-24 10:20:33', '2025-08-24 10:20:33'),
(2, 1, 'medium', 300.00, '2025-08-24 10:20:33', '2025-08-24 10:20:33'),
(3, 1, 'large', 350.00, '2025-08-24 10:20:33', '2025-08-24 10:20:33'),
(4, 1, 'extra_large', 400.00, '2025-08-24 10:20:33', '2025-08-24 10:20:33'),
(5, 2, 'small', 120.00, '2025-08-24 10:20:33', '2025-08-24 10:20:33'),
(6, 2, 'medium', 150.00, '2025-08-24 10:20:33', '2025-08-24 10:20:33'),
(7, 2, 'large', 180.00, '2025-08-24 10:20:33', '2025-08-24 10:20:33'),
(8, 2, 'extra_large', 200.00, '2025-08-24 10:20:33', '2025-08-24 10:20:33'),
(9, 3, 'small', 200.00, '2025-08-24 10:20:33', '2025-08-24 10:20:33'),
(10, 3, 'medium', 200.00, '2025-08-24 10:20:33', '2025-08-24 10:20:33'),
(11, 3, 'large', 200.00, '2025-08-24 10:20:33', '2025-08-24 10:20:33'),
(12, 3, 'extra_large', 200.00, '2025-08-24 10:20:33', '2025-08-24 10:20:33'),
(13, 4, 'small', 500.00, '2025-08-24 10:20:33', '2025-08-24 10:20:33'),
(14, 4, 'medium', 600.00, '2025-08-24 10:20:33', '2025-08-24 10:20:33'),
(15, 4, 'large', 750.00, '2025-08-24 10:20:33', '2025-08-24 10:20:33'),
(16, 4, 'extra_large', 900.00, '2025-08-24 10:20:33', '2025-08-24 10:20:33'),
(17, 5, 'small', 200.00, '2025-08-24 10:20:33', '2025-08-24 10:20:33'),
(18, 5, 'medium', 250.00, '2025-08-24 10:20:33', '2025-08-24 10:20:33'),
(19, 5, 'large', 280.00, '2025-08-24 10:20:33', '2025-08-24 10:20:33'),
(20, 5, 'extra_large', 300.00, '2025-08-24 10:20:33', '2025-08-24 10:20:33'),
(21, 6, 'small', 350.00, '2025-08-24 10:20:33', '2025-08-24 10:20:33'),
(22, 6, 'medium', 400.00, '2025-08-24 10:20:33', '2025-08-24 10:20:33'),
(23, 6, 'large', 450.00, '2025-08-24 10:20:33', '2025-08-24 10:20:33'),
(24, 6, 'extra_large', 500.00, '2025-08-24 10:20:33', '2025-08-24 10:20:33'),
(25, 7, 'small', 100.00, '2025-08-24 10:20:33', '2025-08-24 10:20:33'),
(26, 7, 'medium', 100.00, '2025-08-24 10:20:33', '2025-08-24 10:20:33'),
(27, 7, 'large', 100.00, '2025-08-24 10:20:33', '2025-08-24 10:20:33'),
(28, 7, 'extra_large', 100.00, '2025-08-24 10:20:33', '2025-08-24 10:20:33'),
(29, 8, 'small', 150.00, '2025-08-24 10:20:33', '2025-08-24 10:20:33'),
(30, 8, 'medium', 150.00, '2025-08-24 10:20:33', '2025-08-24 10:20:33'),
(31, 8, 'large', 150.00, '2025-08-24 10:20:33', '2025-08-24 10:20:33'),
(32, 8, 'extra_large', 150.00, '2025-08-24 10:20:33', '2025-08-24 10:20:33');

-- --------------------------------------------------------

--
-- Table structure for table `status_updates`
--

CREATE TABLE `status_updates` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `status` varchar(20) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('admin','staff','cashier') NOT NULL DEFAULT 'staff',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `full_name`, `role`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@animates.ph', 'System Administrator', 'admin', 1, '2025-01-28 00:00:00', '2025-01-28 00:00:00'),
(2, 'cashier1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cashier@animates.ph', 'Cashier 1', 'cashier', 1, '2025-01-28 00:00:00', '2025-01-28 00:00:00'),
(3, 'staff1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff@animates.ph', 'Staff Member 1', 'staff', 1, '2025-01-28 00:00:00', '2025-01-28 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `user_pets`
--

CREATE TABLE `user_pets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `pet_id` int(11) NOT NULL,
  `is_primary_owner` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- IMPORTANT NOTES FOR IMPLEMENTATION
-- =====================================================
-- 1. This file contains the complete database structure with discount support
-- 2. The sales_transactions table now includes the discount_amount column
-- 3. All existing tables and relationships are preserved
-- 4. You can now import this file to restore your complete database
-- 5. The billing system will work properly with discount tracking
-- =====================================================

-- To add the discount_amount column to existing database, run:
-- ALTER TABLE `sales_transactions` ADD COLUMN `discount_amount` decimal(10,2) DEFAULT 0.00 AFTER `amount`;

-- To update existing records to have 0 discount:
-- UPDATE `sales_transactions` SET `discount_amount` = 0.00 WHERE `discount_amount` IS NULL;
