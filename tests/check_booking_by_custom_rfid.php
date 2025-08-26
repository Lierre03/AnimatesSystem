<?php
require_once 'config/database.php';

try {
    $db = getDB();
    
    // Check booking with custom RFID 4BJPCECB
    $stmt = $db->prepare("
        SELECT b.id, b.custom_rfid, b.rfid_card_id, b.status, b.created_at, 
               p.name as pet_name, c.name as owner_name
        FROM bookings b
        LEFT JOIN pets p ON b.pet_id = p.id
        LEFT JOIN customers c ON p.customer_id = c.id
        WHERE b.custom_rfid = '4BJPCECB'
    ");
    $stmt->execute();
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Booking for Custom RFID 4BJPCECB:\n";
    echo "==================================\n\n";
    
    if ($booking) {
        echo "Booking ID: " . $booking['id'] . "\n";
        echo "Custom RFID: " . $booking['custom_rfid'] . "\n";
        echo "RFID Card ID: " . $booking['rfid_card_id'] . "\n";
        echo "Status: " . $booking['status'] . "\n";
        echo "Created: " . $booking['created_at'] . "\n";
        echo "Pet: " . ($booking['pet_name'] ?? 'NULL') . "\n";
        echo "Owner: " . ($booking['owner_name'] ?? 'NULL') . "\n";
    } else {
        echo "No booking found for custom RFID 4BJPCECB\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>


