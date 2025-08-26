<?php
require_once 'config/database.php';

try {
    $db = getDB();
    
    // Test with the specific RFID tag from the response
    $rfidTag = '1BL89OOX';
    
    echo "Testing RFID Tag: $rfidTag\n";
    echo "==========================\n\n";
    
    // First, check if this RFID exists in rfid_cards table
    $stmt = $db->prepare("SELECT * FROM rfid_cards WHERE card_uid = ? OR custom_uid = ?");
    $stmt->execute([$rfidTag, $rfidTag]);
    $rfidCard = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($rfidCard) {
        echo "✓ RFID card found:\n";
        echo "  - ID: " . $rfidCard['id'] . "\n";
        echo "  - Card UID: " . $rfidCard['card_uid'] . "\n";
        echo "  - Custom UID: " . $rfidCard['custom_uid'] . "\n";
        echo "  - Tap Count: " . $rfidCard['tap_count'] . "\n";
        echo "  - Status: " . $rfidCard['status'] . "\n";
        
        // Now test the exact query from rfid_tap.php
        echo "\nTesting RFID Tap Query:\n";
        echo "=======================\n\n";
        
        $stmt2 = $db->prepare("
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
            LEFT JOIN bookings b ON r.id = b.rfid_card_id
            LEFT JOIN pets p ON b.pet_id = p.id
            LEFT JOIN customers c ON p.customer_id = c.id
            WHERE (r.card_uid = ? OR r.custom_uid = ?)
            LIMIT 1
        ");
        
        $stmt2->execute([$rfidTag, $rfidTag]);
        $result = $stmt2->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            echo "✓ Query result found:\n";
            echo "  - RFID ID: " . $result['rfid_id'] . "\n";
            echo "  - Booking ID: " . ($result['booking_id'] ?? 'NULL') . "\n";
            echo "  - Pet Name: " . ($result['pet_name'] ?? 'NULL') . "\n";
            echo "  - Owner: " . ($result['owner_name'] ?? 'NULL') . "\n";
            
            if ($result['booking_id']) {
                // Check booking status
                $stmt3 = $db->prepare("SELECT status FROM bookings WHERE id = ?");
                $stmt3->execute([$result['booking_id']]);
                $bookingStatus = $stmt3->fetch(PDO::FETCH_ASSOC);
                echo "  - Booking Status: " . $bookingStatus['status'] . "\n";
            } else {
                echo "  - No booking linked to this RFID card\n";
                
                // Check if there are any bookings with this RFID (including completed ones)
                $stmt4 = $db->prepare("
                    SELECT b.id, b.custom_rfid, b.rfid_card_id, b.status 
                    FROM bookings b 
                    WHERE b.rfid_card_id = ? OR b.custom_rfid = ?
                    ORDER BY b.created_at DESC 
                    LIMIT 5
                ");
                $stmt4->execute([$result['rfid_id'], $result['custom_uid']]);
                $allBookings = $stmt4->fetchAll(PDO::FETCH_ASSOC);
                
                if ($allBookings) {
                    echo "\nFound bookings with this RFID:\n";
                    foreach ($allBookings as $booking) {
                        echo "  - Booking ID: " . $booking['id'] . ", Status: " . $booking['status'] . "\n";
                    }
                } else {
                    echo "\nNo bookings found with this RFID\n";
                }
            }
        } else {
            echo "✗ Query returned no results\n";
        }
        
    } else {
        echo "✗ RFID card not found in database\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
