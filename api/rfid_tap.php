<?php
// Start output buffering FIRST
ob_start();

// Turn off error display to prevent HTML output
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Include your database connection file and email functions
require_once '../config/database.php';
require_once '../includes/email_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['rfid_tag'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid JSON or missing rfid_tag']);
    exit;
}

$rfidTag = trim($input['rfid_tag']);

if (empty($rfidTag)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'RFID tag cannot be empty']);
    exit;
}

try {
    $db = getDB();
    $db->beginTransaction();
    
    // Find RFID card and booking info
    $stmt = $db->prepare("
        SELECT 
            r.id as rfid_id,
            r.card_uid,
            r.custom_uid,
            r.tap_count,
            b.id as booking_id,
            p.name as pet_name,
            p.type as pet_type,
            p.breed as pet_breed,
            c.name as owner_name,
            c.email as owner_email,
            c.phone as owner_phone
        FROM rfid_cards r
        LEFT JOIN bookings b ON r.id = b.rfid_card_id AND b.status NOT IN ('completed', 'cancelled')
        LEFT JOIN pets p ON b.pet_id = p.id
        LEFT JOIN customers c ON p.customer_id = c.id
        WHERE (r.card_uid = ? OR r.custom_uid = ?)
        LIMIT 1
    ");
    $stmt->execute([$rfidTag, $rfidTag]);
    $rfidData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$rfidData) {
        $db->rollBack();
        ob_clean();
        echo json_encode([
            'success' => false, 
            'message' => 'RFID tag not found: ' . $rfidTag
        ]);
        exit;
    }
    
    // Check if booking is already completed
    $isCompleted = false;
    if ($rfidData['booking_id']) {
        $stmt = $db->prepare("SELECT status FROM bookings WHERE id = ?");
        $stmt->execute([$rfidData['booking_id']]);
        $bookingStatus = $stmt->fetch(PDO::FETCH_ASSOC);
        $isCompleted = ($bookingStatus && $bookingStatus['status'] === 'completed');
    }
    
    // Update tap count (but don't exceed 5 if booking is completed)
    $newTapCount = (int)$rfidData['tap_count'] + 1;
    if ($isCompleted && $newTapCount > 5) {
        $newTapCount = 5; // Cap at 5 for completed bookings
    }
    $stmt = $db->prepare("UPDATE rfid_cards SET tap_count = ? WHERE id = ?");
    $stmt->execute([$newTapCount, $rfidData['rfid_id']]);
    
    // Get status
    $status = getStatusFromTapCount($newTapCount);

    // Normalize status to match bookings.status enum values
    // bookings.status enum: ('checked-in','bathing','grooming','ready','completed','cancelled')
    if ($status === 'ready for pickup') {
        $statusForBooking = 'ready';
    } elseif (in_array($status, ['checked-in','bathing','grooming','completed','cancelled'], true)) {
        $statusForBooking = $status;
    } else {
        $statusForBooking = null; // unknown or non-mapped status -> skip DB update
    }

    // Persist status to booking if we have a linked booking and a valid mapped status
    $bookingUpdated = false;
    $statusChangedTo = null;
    
    if (!empty($rfidData['booking_id']) && $statusForBooking !== null) {
        // Always update the booking status if we have a linked active booking
        $stmt = $db->prepare("UPDATE bookings SET status = ? WHERE id = ?");
        $stmt->execute([$statusForBooking, $rfidData['booking_id']]);
        $bookingUpdated = true;
        $statusChangedTo = $statusForBooking;
    } else {
        // If no active booking found, try to find any recent booking with this RFID
        $stmt = $db->prepare("
            SELECT b.id, b.status 
            FROM bookings b 
            WHERE b.rfid_card_id = ? OR b.custom_rfid = ?
            ORDER BY b.created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$rfidData['rfid_id'], $rfidData['custom_uid']]);
        $anyBooking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($anyBooking && $statusForBooking !== null) {
            // Check if this booking can be updated (not completed/cancelled)
            if (!in_array($anyBooking['status'], ['completed', 'cancelled'])) {
                $stmt = $db->prepare("UPDATE bookings SET status = ? WHERE id = ?");
                $stmt->execute([$statusForBooking, $anyBooking['id']]);
                $bookingUpdated = true;
                $statusChangedTo = $statusForBooking;
            }
        }
    }
    
    $db->commit();
    
    // Send email automatically
    $emailSent = false;
    $emailError = null;
    
    if ($rfidData['booking_id'] && $rfidData['owner_email']) {
        try {
            $emailSent = sendBookingStatusEmail($rfidData['booking_id']);
            if (!$emailSent) {
                $emailError = "Email sending failed - check SMTP configuration";
            }
        } catch (Exception $e) {
            $emailError = "Email error: " . $e->getMessage();
        }
    } else {
        $emailError = $rfidData['booking_id'] ? "No owner email found" : "No booking found";
    }
    
    // Clear any buffered output and send clean JSON
    ob_clean();
    
    // Success response
    echo json_encode([
        'success' => true,
        'message' => 'RFID tap processed successfully! 🎉',
        'data' => [
            'rfid_tag' => $rfidData['custom_uid'] ?: $rfidData['card_uid'],
            'pet_name' => $rfidData['pet_name'],
            'pet_type' => $rfidData['pet_type'],
            'pet_breed' => $rfidData['pet_breed'],
            'owner_name' => $rfidData['owner_name'],
            'owner_email' => $rfidData['owner_email'],
            'previous_tap_count' => (int)$rfidData['tap_count'],
            'new_tap_count' => $newTapCount,
            'status' => $status,
            'booking_id' => $rfidData['booking_id'],
            'booking_updated' => $bookingUpdated,
            'status_changed_to' => $statusChangedTo,
            'status_emoji' => getStatusEmoji($status),
            'email_sent' => $emailSent,
            'email_error' => $emailError
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    
    // Log the error
    error_log("RFID Tap Error: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine());
    
    // Clear any buffered output and send clean error JSON
    ob_clean();
    
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'debug_info' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ]);
}

function getStatusEmoji($status) {
    $emojis = [
        'unknown' => '❓',
        'checked-in' => '✅', 
        'bathing' => '🛁',
        'grooming' => '✂️',
        'ready for pickup' => '🎉'
    ];
    return $emojis[$status] ?? '📋';
}

// End output buffering
ob_end_flush();
?>