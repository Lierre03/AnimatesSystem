-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 28, 2025 at 04:54 PM
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
-- Database: `animates`
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
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  `booking_type` enum('walk_in','online') DEFAULT 'walk_in',
  `welcome_email_sent` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `pet_id`, `rfid_card_id`, `rfid_tag_id`, `custom_rfid`, `total_amount`, `status`, `payment_status`, `payment_method`, `payment_reference`, `payment_platform`, `amount_tendered`, `change_amount`, `payment_date`, `check_in_time`, `estimated_completion`, `actual_completion`, `pickup_time`, `staff_notes`, `updated_by`, `created_at`, `updated_at`, `user_id`, `booking_type`, `welcome_email_sent`) VALUES
(1, 0, 0, NULL, '3T4TO70Z', 200.00, 'grooming', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, '2025-08-28 04:25:50', '2025-08-28 06:25:50', '2025-08-28 04:31:08', NULL, NULL, NULL, '2025-08-28 04:25:50', '2025-08-28 04:43:12', NULL, 'walk_in', 1),
(2, 0, 0, NULL, 'TVTPIV8O', 650.00, 'completed', 'paid', 'cash', '', '', 600.00, 48.00, '2025-08-28 12:01:02', '2025-08-28 04:34:08', '2025-08-28 06:34:08', '2025-08-28 12:01:02', NULL, NULL, NULL, '2025-08-28 04:34:08', '2025-08-28 12:01:02', NULL, 'walk_in', 1),
(3, 0, NULL, NULL, 'TVTPIV8O', 0.00, 'completed', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, '2025-08-28 14:37:37', NULL, '2025-08-28 14:49:12', NULL, NULL, NULL, '2025-08-28 14:37:37', '2025-08-28 14:49:12', NULL, 'walk_in', 0);

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

--
-- Dumping data for table `booking_services`
--

INSERT INTO `booking_services` (`id`, `booking_id`, `service_id`, `price`, `pet_size`) VALUES
(1, 0, 0, 500.00, NULL),
(2, 0, 0, 200.00, NULL),
(3, 0, 0, 200.00, NULL),
(4, 0, 0, 450.00, NULL);

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

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `name`, `phone`, `email`, `address`, `emergency_contact`, `created_at`, `updated_at`, `user_id`, `created_via`) VALUES
(1, 'Ivy Rivera', '0934-782-3472', 'ivyrivera50@gmail.com', NULL, NULL, '2025-08-28 04:22:04', '2025-08-28 04:22:04', NULL, 'walk_in'),
(2, 'Ivy Rivera', '0934-782-3472', 'ivyrivera50@gmail.com', NULL, NULL, '2025-08-28 04:25:50', '2025-08-28 04:25:50', NULL, 'walk_in'),
(3, 'Ivy Rivera', '0934-782-3472', 'ivyrivera50@gmail.com', NULL, NULL, '2025-08-28 04:34:08', '2025-08-28 04:34:08', NULL, 'walk_in');

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

--
-- Dumping data for table `pets`
--

INSERT INTO `pets` (`id`, `customer_id`, `name`, `type`, `pet_type`, `breed`, `age_range`, `size`, `special_notes`, `created_at`, `updated_at`) VALUES
(0, 1, 'Test Pet', 'dog', NULL, 'mixed', NULL, NULL, NULL, '2025-08-28 14:37:37', '2025-08-28 14:37:37'),
(1, 0, 'Buddy', 'dog', 'dog', 'boxer', 'young', NULL, '', '2025-08-28 04:22:04', '2025-08-28 04:22:04'),
(2, 0, 'Buddy', 'dog', 'dog', 'bluetick', NULL, NULL, '', '2025-08-28 04:25:50', '2025-08-28 04:25:50'),
(3, 0, 'Buddy', 'dog', 'dog', 'brabancon', 'young', NULL, '', '2025-08-28 04:34:08', '2025-08-28 04:34:08');

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

--
-- Dumping data for table `rfid_cards`
--

INSERT INTO `rfid_cards` (`id`, `card_uid`, `custom_uid`, `firebase_doc_id`, `tap_count`, `max_taps`, `created_at`, `updated_at`, `is_active`, `last_firebase_sync`, `validation_time_ms`, `device_source`, `status`, `is_currently_booked`) VALUES
(1, '73:77:f8:39', 'TVTPIV8O', NULL, 5, 5, '2025-08-28 04:19:37', '2025-08-28 14:49:12', 1, '2025-08-28 14:49:12', 3000, 'ESP32-RFID-Scanner', 'active', 0);

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

--
-- Dumping data for table `rfid_tap_history`
--

INSERT INTO `rfid_tap_history` (`id`, `rfid_card_id`, `card_uid`, `custom_uid`, `tap_number`, `firebase_doc_id`, `tapped_at`, `device_info`, `wifi_network`, `signal_strength`, `validation_status`, `readable_time`, `timestamp_value`, `rfid_scanner_status`, `project_id`) VALUES
(1, 1, '73:77:f8:39', 'TVTPIV8O', 2, NULL, '2025-08-28 14:38:46', 'ESP32-RFID-Scanner', 'HUAWEI-2.4G-x6Nj', -53, 'approved', '2025-08-28 12:28:47', '2025-08-28 04:28:47', 'OK', NULL),
(2, 0, '73:77:f8:39', 'WRFXVJTV', 2, NULL, '2025-08-28 04:19:37', 'ESP32-RFID-Scanner', 'HUAWEI-2.4G-x6Nj', -51, 'approved', '2025-08-28 12:19:38', '2025-08-28 04:19:38', 'OK', NULL),
(3, 0, '73:77:f8:39', 'WRFXVJTV', 3, NULL, '2025-08-28 04:19:53', 'ESP32-RFID-Scanner', 'HUAWEI-2.4G-x6Nj', -53, 'approved', '2025-08-28 12:19:54', '2025-08-28 04:19:54', 'OK', NULL),
(4, 0, '73:77:f8:39', 'WRFXVJTV', 4, NULL, '2025-08-28 04:20:12', 'ESP32-RFID-Scanner', 'HUAWEI-2.4G-x6Nj', -51, 'approved', '2025-08-28 12:20:10', '2025-08-28 04:20:10', 'OK', NULL),
(5, 0, '73:77:f8:39', 'WRFXVJTV', 5, NULL, '2025-08-28 04:21:06', 'ESP32-RFID-Scanner', 'HUAWEI-2.4G-x6Nj', -52, 'approved', '2025-08-28 12:21:07', '2025-08-28 04:21:07', 'OK', NULL),
(6, 0, '73:77:f8:39', 'UWRE2YQ4', 1, NULL, '2025-08-28 04:21:21', 'ESP32-RFID-Scanner', 'HUAWEI-2.4G-x6Nj', -50, 'approved', '2025-08-28 12:21:22', '2025-08-28 04:21:22', 'OK', NULL),
(7, 0, '73:77:f8:39', 'UWRE2YQ4', 2, NULL, '2025-08-28 04:21:36', 'ESP32-RFID-Scanner', 'HUAWEI-2.4G-x6Nj', -53, 'approved', '2025-08-28 12:21:37', '2025-08-28 04:21:37', 'OK', NULL),
(8, 0, '73:77:f8:39', 'UWRE2YQ4', 3, NULL, '2025-08-28 04:24:01', 'ESP32-RFID-Scanner', 'HUAWEI-2.4G-x6Nj', -53, 'approved', '2025-08-28 12:24:02', '2025-08-28 04:24:02', 'OK', NULL),
(9, 0, '73:77:f8:39', 'UWRE2YQ4', 3, NULL, '2025-08-28 04:24:04', 'ESP32-RFID-Scanner', 'HUAWEI-2.4G-x6Nj', -53, 'approved', '2025-08-28 12:24:05', '2025-08-28 04:24:05', 'OK', NULL),
(10, 0, '73:77:f8:39', 'UWRE2YQ4', 3, NULL, '2025-08-28 04:24:17', 'ESP32-RFID-Scanner', 'HUAWEI-2.4G-x6Nj', -50, 'approved', '2025-08-28 12:24:19', '2025-08-28 04:24:19', 'OK', NULL),
(11, 0, '73:77:f8:39', 'UWRE2YQ4', 4, NULL, '2025-08-28 04:24:42', 'ESP32-RFID-Scanner', 'HUAWEI-2.4G-x6Nj', -50, 'approved', '2025-08-28 12:24:43', '2025-08-28 04:24:43', 'OK', NULL),
(12, 0, '73:77:f8:39', 'UWRE2YQ4', 5, NULL, '2025-08-28 04:25:28', 'ESP32-RFID-Scanner', 'HUAWEI-2.4G-x6Nj', -52, 'approved', '2025-08-28 12:25:30', '2025-08-28 04:25:30', 'OK', NULL),
(13, 0, '73:77:f8:39', '3T4TO70Z', 1, NULL, '2025-08-28 04:25:43', 'ESP32-RFID-Scanner', 'HUAWEI-2.4G-x6Nj', -53, 'approved', '2025-08-28 12:25:44', '2025-08-28 04:25:44', 'OK', NULL),
(14, 0, '73:77:f8:39', '3T4TO70Z', 2, NULL, '2025-08-28 04:29:37', 'ESP32-RFID-Scanner', 'HUAWEI-2.4G-x6Nj', -59, 'approved', '2025-08-28 12:29:36', '2025-08-28 04:29:36', 'OK', NULL),
(15, 0, '73:77:f8:39', '3T4TO70Z', 2, NULL, '2025-08-28 04:29:40', 'ESP32-RFID-Scanner', 'HUAWEI-2.4G-x6Nj', -56, 'approved', '2025-08-28 12:29:41', '2025-08-28 04:29:41', 'OK', NULL),
(16, 0, '73:77:f8:39', '3T4TO70Z', 2, NULL, '2025-08-28 04:30:00', 'ESP32-RFID-Scanner', 'HUAWEI-2.4G-x6Nj', -49, 'approved', '2025-08-28 12:30:01', '2025-08-28 04:30:01', 'OK', NULL),
(17, 0, '73:77:f8:39', '3T4TO70Z', 3, NULL, '2025-08-28 04:30:32', 'ESP32-RFID-Scanner', 'HUAWEI-2.4G-x6Nj', -50, 'approved', '2025-08-28 12:30:33', '2025-08-28 04:30:33', 'OK', NULL),
(18, 0, '73:77:f8:39', '3T4TO70Z', 4, NULL, '2025-08-28 04:30:46', 'ESP32-RFID-Scanner', 'HUAWEI-2.4G-x6Nj', -52, 'approved', '2025-08-28 12:30:47', '2025-08-28 04:30:47', 'OK', NULL),
(19, 0, '73:77:f8:39', '3T4TO70Z', 5, NULL, '2025-08-28 04:31:08', 'ESP32-RFID-Scanner', 'HUAWEI-2.4G-x6Nj', -53, 'approved', '2025-08-28 12:31:09', '2025-08-28 04:31:09', 'OK', NULL),
(20, 0, '73:77:f8:39', 'TVTPIV8O', 1, NULL, '2025-08-28 04:34:04', 'ESP32-RFID-Scanner', 'HUAWEI-2.4G-x6Nj', -53, 'approved', '2025-08-28 12:34:05', '2025-08-28 04:34:05', 'OK', NULL),
(21, 0, '73:77:f8:39', 'TVTPIV8O', 2, NULL, '2025-08-28 04:34:30', 'ESP32-RFID-Scanner', 'HUAWEI-2.4G-x6Nj', -55, 'approved', '2025-08-28 12:34:31', '2025-08-28 04:34:31', 'OK', NULL),
(22, 0, '73:77:f8:39', 'TVTPIV8O', 3, NULL, '2025-08-28 04:43:12', 'ESP32-RFID-Scanner', 'HUAWEI-2.4G-x6Nj', -52, 'approved', '2025-08-28 12:43:13', '2025-08-28 04:43:13', 'OK', NULL),
(23, 0, '73:77:f8:39', 'TVTPIV8O', 4, NULL, '2025-08-28 04:46:14', 'ESP32-RFID-Scanner', 'HUAWEI-2.4G-x6Nj', -49, 'approved', '2025-08-28 12:46:16', '2025-08-28 04:46:16', 'OK', NULL),
(24, 0, '73:77:f8:39', 'TVTPIV8O', 5, NULL, '2025-08-28 04:46:51', 'ESP32-RFID-Scanner', 'HUAWEI-2.4G-x6Nj', -50, 'approved', '2025-08-28 12:46:52', '2025-08-28 04:46:52', 'OK', NULL),
(25, 0, '73:77:f8:39', 'TVTPIV8O', 2, NULL, '2025-08-28 14:32:04', 'ESP32-RFID-Scanner', 'HUAWEI-2.4G-x6Nj', -53, 'approved', '2025-08-28 12:28:47', '2025-08-28 04:28:47', 'OK', NULL),
(26, 0, '73:77:f8:39', 'TVTPIV8O', 2, NULL, '2025-08-28 14:32:35', 'ESP32-RFID-Scanner', 'HUAWEI-2.4G-x6Nj', -53, 'approved', '2025-08-28 12:28:47', '2025-08-28 04:28:47', 'OK', NULL),
(27, 1, '73:77:f8:39', 'TVTPIV8O', 3, NULL, '2025-08-28 14:45:49', 'ESP32-RFID-Scanner', 'HUAWEI-2.4G-x6Nj', -53, 'approved', '2025-08-28 12:28:47', '2025-08-28 04:28:47', 'OK', NULL),
(28, 1, '73:77:f8:39', 'TVTPIV8O', 4, NULL, '2025-08-28 14:47:36', 'ESP32-RFID-Scanner', 'HUAWEI-2.4G-x6Nj', -53, 'approved', '2025-08-28 12:28:47', '2025-08-28 04:28:47', 'OK', NULL),
(29, 1, '73:77:f8:39', 'TVTPIV8O', 5, NULL, '2025-08-28 14:49:12', 'ESP32-RFID-Scanner', 'HUAWEI-2.4G-x6Nj', -53, 'approved', '2025-08-28 12:28:47', '2025-08-28 04:28:47', 'OK', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `sales_transactions`
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

--
-- Dumping data for table `sales_transactions`
--

INSERT INTO `sales_transactions` (`id`, `booking_id`, `transaction_reference`, `amount`, `discount_amount`, `payment_method`, `payment_platform`, `status`, `void_reason`, `voided_by`, `voided_at`, `created_at`) VALUES
(1, 2, 'TXN-20250828-F2A1BA79', 585.00, 65.00, 'cash', '', 'completed', NULL, NULL, NULL, '2025-08-28 11:43:42'),
(2, 2, 'TXN-20250828-18A39626', 585.00, 65.00, 'cash', '', 'completed', NULL, NULL, NULL, '2025-08-28 11:43:48'),
(3, 2, 'TXN-20250828-97C7086B', 650.00, 0.00, 'cash', '', 'completed', NULL, NULL, NULL, '2025-08-28 11:44:11'),
(4, 2, 'TXN-20250828-C352FFB9', 552.00, 98.00, 'cash', '', 'completed', NULL, NULL, NULL, '2025-08-28 12:01:02');

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

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `name`, `price`, `duration_minutes`, `category`, `description`, `is_active`) VALUES
(1, 'Full Grooming Package', 500.00, 60, 'basic', 'Full Grooming Package', 1),
(2, 'Ear Cleaning', 200.00, 60, 'basic', 'Ear Cleaning', 1),
(3, 'De-shedding Treatment', 450.00, 60, 'basic', 'De-shedding Treatment', 1);

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

--
-- Dumping data for table `status_updates`
--

INSERT INTO `status_updates` (`id`, `booking_id`, `status`, `notes`, `created_at`) VALUES
(1, 3, 'bathing', 'Status automatically updated via RFID tap #2', '2025-08-28 14:38:46'),
(2, 0, 'checked-in', 'Initial check-in completed', '2025-08-28 04:22:04'),
(3, 0, 'checked-in', 'Initial check-in completed', '2025-08-28 04:25:50'),
(4, 0, 'bathing', 'Status automatically updated via RFID tap #2', '2025-08-28 04:29:37'),
(5, 0, 'grooming', 'Status automatically updated via RFID tap #3', '2025-08-28 04:30:32'),
(6, 0, 'ready', 'Status automatically updated via RFID tap #4', '2025-08-28 04:30:46'),
(7, 0, 'completed', 'Service completed! Pet picked up via RFID tap #5', '2025-08-28 04:31:08'),
(8, 0, 'checked-in', 'Initial check-in completed', '2025-08-28 04:34:08'),
(9, 0, 'bathing', 'Status automatically updated via RFID tap #2', '2025-08-28 04:34:30'),
(10, 0, 'grooming', 'Status automatically updated via RFID tap #3', '2025-08-28 04:43:12'),
(11, 2, 'ready', 'Status automatically updated via RFID tap #4', '2025-08-28 04:46:14'),
(12, 2, 'completed', 'Service completed! Pet picked up via RFID tap #5', '2025-08-28 04:46:51'),
(13, 3, 'grooming', 'Status automatically updated via RFID tap #3', '2025-08-28 14:45:49'),
(14, 3, 'ready', 'Status automatically updated via RFID tap #4', '2025-08-28 14:47:36'),
(15, 3, 'completed', 'Service completed! Pet picked up via RFID tap #5', '2025-08-28 14:49:12');

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
(1, 'admin', '$2y$10$h40sViPWyPY88qxdStAR/.4aVn0jQPdPdK3qa06arP1kCWxI7vhvm', 'admin@animates.ph', 'System Administrator', 'admin', 1, '2025-01-28 00:00:00', '2025-08-28 04:49:29'),
(2, 'cashier1', '$2y$10$/mtkwNTqWT8lj9/ErO6Haeye.Y6LrI05PanGFFqCbYnYc.SfARQu2', 'cashier@animates.ph', 'Cashier 1', 'cashier', 1, '2025-01-28 00:00:00', '2025-08-28 04:49:29'),
(3, 'staff1', '$2y$10$sW29vRwY/8V1.KOLyhfp1eugbVH3s5Rts0miH9fbjsJV6rkyHOdQG', 'staff@animates.ph', 'Staff Member 1', 'staff', 1, '2025-01-28 00:00:00', '2025-08-28 04:49:29'),
(4, 'ivyrivera50', '$2y$10$wnwyqR6qpg9m.5kN.0khu.ZSWIpFQY11RF9pJrHrXgz8bes2Du/L.', 'ivyrivera50@gmail.com', 'Test test', 'staff', 1, '2025-08-28 11:35:30', '2025-08-28 12:43:53'),
(5, 'cashier4', '$2y$10$DCkL93xiqQBq.qg4lljde.z3QyQjF1sjxxgwW2Tw6CfXKsAqovhO2', 'cashier4@animates.ph', 'Cashier4 Cashier4', 'staff', 0, '2025-08-28 12:19:00', '2025-08-28 12:45:27'),
(6, 'cashier6', '$2y$10$yxpNL2gsMtCSJhYVfbDcAuEwv/gOPV.bcQg25B1htRMHg7LPp5PCy', 'cashier6@animates.ph', 'Cashier6 Staff', 'cashier', 0, '2025-08-28 12:33:44', '2025-08-28 12:44:09'),
(7, 'cashier7', '$2y$10$F8qgmtj5bDleneWRVTyCt.T7XSR8CDc8zc7GbyGAkhHF17fA6F0h2', 'cashier7@animates.ph', 'cashier7 Cashier7', 'cashier', 1, '2025-08-28 12:46:39', '2025-08-28 12:46:39');

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

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `appointment_services`
--
ALTER TABLE `appointment_services`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `app_config`
--
ALTER TABLE `app_config`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pet_id` (`pet_id`),
  ADD KEY `idx_custom_rfid` (`custom_rfid`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `booking_services`
--
ALTER TABLE `booking_services`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pets`
--
ALTER TABLE `pets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pet_sizes`
--
ALTER TABLE `pet_sizes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rfid_cards`
--
ALTER TABLE `rfid_cards`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rfid_tags`
--
ALTER TABLE `rfid_tags`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rfid_tap_history`
--
ALTER TABLE `rfid_tap_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sales_transactions`
--
ALTER TABLE `sales_transactions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `services2`
--
ALTER TABLE `services2`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `service_pricing`
--
ALTER TABLE `service_pricing`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `status_updates`
--
ALTER TABLE `status_updates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_pets`
--
ALTER TABLE `user_pets`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `appointment_services`
--
ALTER TABLE `appointment_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `app_config`
--
ALTER TABLE `app_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `rfid_cards`
--
ALTER TABLE `rfid_cards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `rfid_tap_history`
--
ALTER TABLE `rfid_tap_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `status_updates`
--
ALTER TABLE `status_updates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
