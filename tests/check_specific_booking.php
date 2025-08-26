<?php
require_once 'config/database.php';

try {
    $db = getDB();
    
    // Check booking with RFID card ID 35
    $stmt = $db->prepare("
        SELECT b.id, b.custom_rfid, b.rfid_card_id, b.status, b.created_at, 
               p.name as pet_name, c.name as owner_name
        FROM bookings b
        LEFT JOIN pets p ON b.pet_id = p.id
        LEFT JOIN customers c ON p.customer_id = c.id
        WHERE b.rfid_card_id = 35
    ");
    $stmt->execute();
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Booking for RFID Card ID 35:\n";
    echo "============================\n\n";
    
    if ($booking) {
        echo "Booking ID: " . $booking['id'] . "\n";
        echo "Custom RFID: " . ($booking['custom_rfid'] ?? 'NULL') . "\n";
        echo "RFID Card ID: " . $booking['rfid_card_id'] . "\n";
        echo "Status: " . $booking['status'] . "\n";
        echo "Created: " . $booking['created_at'] . "\n";
        echo "Pet: " . ($booking['pet_name'] ?? 'NULL') . "\n";
        echo "Owner: " . ($booking['owner_name'] ?? 'NULL') . "\n";
    } else {
        echo "No booking found for RFID card ID 35\n";
    }
    
    // Also check RFID card 35 details
    echo "\nRFID Card 35 Details:\n";
    echo "=====================\n\n";
    
    $stmt2 = $db->prepare("SELECT * FROM rfid_cards WHERE id = 35");
    $stmt2->execute();
    $rfidCard = $stmt2->fetch(PDO::FETCH_ASSOC);
    
    if ($rfidCard) {
        echo "ID: " . $rfidCard['id'] . "\n";
        echo "Card UID: " . $rfidCard['card_uid'] . "\n";
        echo "Custom UID: " . $rfidCard['custom_uid'] . "\n";
        echo "Tap Count: " . $rfidCard['tap_count'] . "\n";
        echo "Status: " . $rfidCard['status'] . "\n";
    } else {
        echo "RFID card 35 not found\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
