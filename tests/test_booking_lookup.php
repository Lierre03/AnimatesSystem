<?php
require_once 'config/database.php';

try {
    $db = getDB();
    
    // Test the exact query from rfid_tap.php
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
        LEFT JOIN bookings b ON (r.id = b.rfid_card_id OR r.custom_uid = b.custom_rfid)
        LEFT JOIN pets p ON b.pet_id = p.id
        LEFT JOIN customers c ON p.customer_id = c.id
        WHERE (r.card_uid = ? OR r.custom_uid = ?)
        AND (b.status IS NULL OR b.status NOT IN ('completed', 'cancelled'))
        ORDER BY b.created_at DESC
        LIMIT 1
    ");
    
    $rfidTag = '4c:3f:b6:01'; // This is the card_uid for RFID card ID 35 which has tap_count 1
    $stmt->execute([$rfidTag, $rfidTag]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Testing RFID Tag: $rfidTag\n";
    echo "================================\n\n";
    
    if ($result) {
        echo "✓ RFID card found:\n";
        echo "  - RFID ID: " . $result['rfid_id'] . "\n";
        echo "  - Custom UID: " . $result['custom_uid'] . "\n";
        echo "  - Tap Count: " . $result['tap_count'] . "\n";
        
        if ($result['booking_id']) {
            echo "\n✓ Booking found:\n";
            echo "  - Booking ID: " . $result['booking_id'] . "\n";
            echo "  - Pet Name: " . $result['pet_name'] . "\n";
            echo "  - Owner: " . $result['owner_name'] . "\n";
            echo "  - Email: " . $result['owner_email'] . "\n";
            
            // Check current booking status
            $stmt2 = $db->prepare("SELECT status FROM bookings WHERE id = ?");
            $stmt2->execute([$result['booking_id']]);
            $bookingStatus = $stmt2->fetch(PDO::FETCH_ASSOC);
            
            echo "  - Current Status: " . $bookingStatus['status'] . "\n";
            
            // Calculate what the new status should be
            $newTapCount = (int)$result['tap_count'] + 1;
            $statusMap = [
                1 => 'checked-in',
                2 => 'bathing', 
                3 => 'grooming',
                4 => 'ready',
                5 => 'completed'
            ];
            $newStatus = $statusMap[$newTapCount] ?? 'unknown';
            
            echo "  - Next Tap Count: $newTapCount\n";
            echo "  - New Status Should Be: $newStatus\n";
            
        } else {
            echo "\n✗ No active booking found for this RFID tag\n";
            
            // Check if there are any bookings with this RFID (including completed ones)
            $stmt3 = $db->prepare("SELECT id, custom_rfid, status FROM bookings WHERE custom_rfid = ?");
            $stmt3->execute([$rfidTag]);
            $allBookings = $stmt3->fetchAll(PDO::FETCH_ASSOC);
            
            if ($allBookings) {
                echo "\nFound bookings with this RFID (including completed):\n";
                foreach ($allBookings as $booking) {
                    echo "  - Booking ID: " . $booking['id'] . ", Status: " . $booking['status'] . "\n";
                }
            } else {
                echo "\nNo bookings found with RFID: $rfidTag\n";
            }
        }
        
    } else {
        echo "✗ RFID tag not found in database\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
