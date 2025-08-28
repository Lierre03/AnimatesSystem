<?php
require_once '../config/database.php';
require_once '../includes/email_functions.php'; // Add this line

// Set headers for API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// UPDATED main endpoint logic to handle completion email
try {
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON data');
    }
    
    // Normalize and validate input to be tolerant of device payloads
    // Fallbacks and coercions
    $normalized = [];
    $normalized['card_uid'] = isset($input['card_uid']) ? trim((string)$input['card_uid']) : '';
    $normalized['custom_uid'] = isset($input['custom_uid']) ? strtoupper(trim((string)$input['custom_uid'])) : '';
    // Accept either tap_count or tap_number; coerce to int and clamp 1..5
    $tapCountIncoming = null;
    if (isset($input['tap_count'])) {
        $tapCountIncoming = (int)$input['tap_count'];
    } elseif (isset($input['tap_number'])) {
        $tapCountIncoming = (int)$input['tap_number'];
    }
    if (!is_int($tapCountIncoming) || $tapCountIncoming <= 0) {
        $tapCountIncoming = 1;
    }
    if ($tapCountIncoming > 5) {
        $tapCountIncoming = 5;
    }
    $normalized['tap_count'] = $tapCountIncoming;
    $normalized['tap_number'] = $tapCountIncoming;
    $normalized['max_taps'] = isset($input['max_taps']) ? (int)$input['max_taps'] : 5;
    if ($normalized['max_taps'] <= 0) { $normalized['max_taps'] = 5; }
    $normalized['device_info'] = isset($input['device_info']) ? trim((string)$input['device_info']) : null;
    $normalized['wifi_network'] = $input['wifi_network'] ?? null;
    $normalized['signal_strength'] = isset($input['signal_strength']) ? (int)$input['signal_strength'] : null;
    $normalized['validation_status'] = isset($input['validation_status']) ? (string)$input['validation_status'] : 'approved';
    $normalized['readable_time'] = isset($input['readable_time']) ? (string)$input['readable_time'] : null;
    $normalized['timestamp_value'] = isset($input['timestamp_value']) ? (string)$input['timestamp_value'] : null;
    $normalized['rfid_scanner_status'] = isset($input['rfid_scanner_status']) ? (string)$input['rfid_scanner_status'] : null;

    // Basic required fields check
    if ($normalized['card_uid'] === '' || $normalized['custom_uid'] === '') {
        throw new Exception('Missing required fields: card_uid/custom_uid');
    }

    // Start transaction
    $db->beginTransaction();
    
    // 1. Insert/Update RFID card record
    $cardId = handleRFIDCard($db, $normalized);
    
    // 2. Insert tap history
    insertTapHistory($db, $cardId, $normalized);
    
    // 3. Update booking status based on tap count
    $bookingResult = updateBookingStatus($db, $cardId, $normalized);
    
    // Commit transaction before sending email
    $db->commit();
    
    // 4. Send email notification based on booking status
    $emailSent = false;
    if ($bookingResult['updated'] && $bookingResult['booking_id']) {
        try {
            error_log("RFID: Attempting to send email for booking ID: " . $bookingResult['booking_id']);
            error_log("RFID: Is completion? " . ($bookingResult['is_completion'] ? 'YES' : 'NO'));
            
            // Check if this is completion (tap 5)
            if ($bookingResult['is_completion']) {
                // Send completion/pickup email - NEW
                error_log("RFID: Calling sendCompletionEmail for booking ID: " . $bookingResult['booking_id']);
                $emailSent = sendCompletionEmail($bookingResult['booking_id']);
                error_log("RFID: sendCompletionEmail result: " . ($emailSent ? 'SUCCESS' : 'FAILED'));
            } else {
                // Send regular status update email
                error_log("RFID: Calling sendBookingStatusEmail for booking ID: " . $bookingResult['booking_id']);
                $emailSent = sendBookingStatusEmail($bookingResult['booking_id']);
                error_log("RFID: sendBookingStatusEmail result: " . ($emailSent ? 'SUCCESS' : 'FAILED'));
            }
        } catch (Exception $emailError) {
            error_log("RFID: Email sending failed for booking {$bookingResult['booking_id']}: " . $emailError->getMessage());
            error_log("RFID: Email error trace: " . $emailError->getTraceAsString());
            // Don't fail the API call if email fails
        }
    } else {
        error_log("RFID: No email sent - booking not updated or no booking ID");
        error_log("RFID: updated=" . ($bookingResult['updated'] ? 'true' : 'false') . ", booking_id=" . ($bookingResult['booking_id'] ?? 'null'));
    }
    
    echo json_encode([
        'success' => true,
        'card_id' => $cardId,
        'custom_uid' => $normalized['custom_uid'],
        'tap_count' => $normalized['tap_count'],
        'booking_updated' => $bookingResult['updated'],
        'booking_id' => $bookingResult['booking_id'],
        'status_changed_to' => $bookingResult['new_status'],
        'is_completion' => $bookingResult['is_completion'],
        'email_sent' => $emailSent,
        'message' => 'RFID data saved successfully' . 
                    ($bookingResult['updated'] ? ' and booking status updated' : '') .
                    ($emailSent ? ' and email notification sent' : '') .
                    ($bookingResult['is_completion'] ? ' - Service completed!' : '')
    ]);
    
} catch(Exception $e) {
    if ($db->inTransaction()) {
        $db->rollback();
    }
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleRFIDCard($db, $input) {
    // Check if card exists
    $stmt = $db->prepare("SELECT id, tap_count FROM rfid_cards WHERE card_uid = ?");
    $stmt->execute([$input['card_uid']]);
    $existingCard = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingCard) {
        // Update existing card
        $stmt = $db->prepare("
            UPDATE rfid_cards 
            SET custom_uid = ?, tap_count = ?, updated_at = NOW(), 
                last_firebase_sync = NOW(), device_source = ?
            WHERE card_uid = ?
        ");
        $stmt->execute([
            $input['custom_uid'],
            $input['tap_count'],
            $input['device_info'],
            $input['card_uid']
        ]);
        
        return $existingCard['id'];
    } else {
        // Insert new card
        $stmt = $db->prepare("
            INSERT INTO rfid_cards 
            (card_uid, custom_uid, tap_count, max_taps, device_source, status) 
            VALUES (?, ?, ?, ?, ?, 'active')
        ");
        $stmt->execute([
            $input['card_uid'],
            $input['custom_uid'],
            $input['tap_count'],
            $input['max_taps'],
            $input['device_info']
        ]);
        
        return $db->lastInsertId();
    }
}

function insertTapHistory($db, $cardId, $input) {
    $stmt = $db->prepare("
        INSERT INTO rfid_tap_history 
        (rfid_card_id, card_uid, custom_uid, tap_number, tapped_at, 
         device_info, wifi_network, signal_strength, validation_status, 
         readable_time, timestamp_value, rfid_scanner_status) 
        VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $cardId,
        $input['card_uid'],
        $input['custom_uid'],
        $input['tap_number'],
        $input['device_info'],
        $input['wifi_network'] ?? null,
        $input['signal_strength'] ?? null,
        $input['validation_status'],
        $input['readable_time'],
        $input['timestamp_value'],
        $input['rfid_scanner_status']
    ]);
}


function updateBookingStatus($db, $cardId, $input) {
    $tapCount = $input['tap_count'];
    $customUID = $input['custom_uid'];
    
    error_log("RFID: updateBookingStatus called with cardId=$cardId, customUID=$customUID, tapCount=$tapCount");
    
    // UPDATED status mapping to include tap 5 for completion
    $statusMap = [
        1 => 'checked-in',
        2 => 'bathing', 
        3 => 'grooming',
        4 => 'ready',
        5 => 'completed'  // NEW - tap 5 completes the service
    ];
    
    // Return result structure
    $result = [
        'updated' => false,
        'booking_id' => null,
        'new_status' => null,
        'is_completion' => false  // NEW - track if this is completion tap
    ];
    
    // Skip if tap count is not in our mapping
    if (!isset($statusMap[$tapCount])) {
        error_log("RFID: Invalid tap count: $tapCount");
        return $result;
    }
    
    $newStatus = $statusMap[$tapCount];
    $result['is_completion'] = ($tapCount === 5);  // NEW - check if completion
    
    // Find booking using custom_rfid (which matches custom_uid from device)
    // FIXED: Use custom_rfid directly instead of joining with rfid_cards
    $stmt = $db->prepare("
        SELECT b.id, b.status, b.custom_rfid
        FROM bookings b
        WHERE b.custom_rfid = ? 
        AND b.status NOT IN ('completed', 'cancelled')
        ORDER BY b.created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$customUID]); // Use custom_uid from device
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    error_log("RFID: Looking for booking with custom_rfid=$customUID, found: " . json_encode($booking));
    
    if (!$booking) {
        // No active booking found for this custom UID
        error_log("RFID: No active booking found for custom_uid: $customUID");
        return $result;
    }
    
    // Check if status actually needs updating
    if ($booking['status'] === $newStatus) {
        error_log("RFID: Status already $newStatus, no update needed");
        $result['updated'] = true;
        $result['booking_id'] = $booking['id'];
        $result['new_status'] = $newStatus;
        return $result;
    }
    
    error_log("RFID: Updating booking ID {$booking['id']} from status '{$booking['status']}' to '$newStatus'");
    
    // Update booking status
    $stmt = $db->prepare("
        UPDATE bookings 
        SET status = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$newStatus, $booking['id']]);
    
    // Add status update record
    $stmt = $db->prepare("
        INSERT INTO status_updates (booking_id, status, notes, created_at) 
        VALUES (?, ?, ?, NOW())
    ");
    
    $notes = "Status automatically updated via RFID tap #" . $tapCount;
    if ($tapCount === 5) {
        $notes = "Service completed! Pet picked up via RFID tap #" . $tapCount;
    }
    
    $stmt->execute([
        $booking['id'],
        $newStatus,
        $notes
    ]);
    
    // If status is 'ready', update actual_completion time (tap 4)
    if ($newStatus === 'ready') {
        $stmt = $db->prepare("
            UPDATE bookings 
            SET actual_completion = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$booking['id']]);
    }
    
    // If status is 'completed', update pickup time (tap 5) - NEW
    if ($newStatus === 'completed') {
        $stmt = $db->prepare("
            UPDATE bookings 
            SET actual_completion = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$booking['id']]);
        
        // Reset RFID card is_currently_booked flag when service is completed
        $stmt = $db->prepare("
            UPDATE rfid_cards 
            SET is_currently_booked = 0 
            WHERE custom_uid = ?
        ");
        $stmt->execute([$customUID]);
        
        error_log("RFID: Reset is_currently_booked to 0 for RFID card: $customUID");
    }
    
    // Update result
    $result['updated'] = true;
    $result['booking_id'] = $booking['id'];
    $result['new_status'] = $newStatus;
    
    error_log("RFID: Successfully updated booking ID {$booking['id']} to status '$newStatus'");
    
    return $result;
}

?>