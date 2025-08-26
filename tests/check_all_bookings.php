<?php
require_once 'config/database.php';

try {
    $db = getDB();
    
    // Check all bookings
    $stmt = $db->prepare("
        SELECT b.id, b.custom_rfid, b.rfid_card_id, b.status, b.created_at, 
               p.name as pet_name, c.name as owner_name
        FROM bookings b
        LEFT JOIN pets p ON b.pet_id = p.id
        LEFT JOIN customers c ON p.customer_id = c.id
        ORDER BY b.created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "All Recent Bookings:\n";
    echo "===================\n\n";
    
    if ($bookings) {
        foreach ($bookings as $booking) {
            echo "Booking ID: " . $booking['id'] . "\n";
            echo "Custom RFID: " . ($booking['custom_rfid'] ?? 'NULL') . "\n";
            echo "RFID Card ID: " . ($booking['rfid_card_id'] ?? 'NULL') . "\n";
            echo "Status: " . $booking['status'] . "\n";
            echo "Created: " . $booking['created_at'] . "\n";
            echo "Pet: " . ($booking['pet_name'] ?? 'NULL') . "\n";
            echo "Owner: " . ($booking['owner_name'] ?? 'NULL') . "\n";
            echo "---\n";
        }
    } else {
        echo "No bookings found in database\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
