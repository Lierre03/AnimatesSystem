<?php
require_once 'config/database.php';

try {
    $db = getDB();
    
    // Check all RFID cards
    $stmt = $db->prepare("SELECT id, card_uid, custom_uid, tap_count, status FROM rfid_cards ORDER BY id DESC LIMIT 10");
    $stmt->execute();
    $rfidCards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "RFID Cards in Database:\n";
    echo "======================\n\n";
    
    if ($rfidCards) {
        foreach ($rfidCards as $card) {
            echo "ID: " . $card['id'] . "\n";
            echo "Card UID: " . $card['card_uid'] . "\n";
            echo "Custom UID: " . $card['custom_uid'] . "\n";
            echo "Tap Count: " . $card['tap_count'] . "\n";
            echo "Status: " . $card['status'] . "\n";
            echo "---\n";
        }
    } else {
        echo "No RFID cards found in database\n";
    }
    
    // Check recent bookings with RFID
    echo "\nRecent Bookings with RFID:\n";
    echo "==========================\n\n";
    
    $stmt2 = $db->prepare("
        SELECT b.id, b.custom_rfid, b.rfid_card_id, b.status, p.name as pet_name, c.name as owner_name
        FROM bookings b
        LEFT JOIN pets p ON b.pet_id = p.id
        LEFT JOIN customers c ON p.customer_id = c.id
        WHERE b.custom_rfid IS NOT NULL OR b.rfid_card_id IS NOT NULL
        ORDER BY b.created_at DESC
        LIMIT 10
    ");
    $stmt2->execute();
    $bookings = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    if ($bookings) {
        foreach ($bookings as $booking) {
            echo "Booking ID: " . $booking['id'] . "\n";
            echo "Custom RFID: " . ($booking['custom_rfid'] ?? 'NULL') . "\n";
            echo "RFID Card ID: " . ($booking['rfid_card_id'] ?? 'NULL') . "\n";
            echo "Status: " . $booking['status'] . "\n";
            echo "Pet: " . $booking['pet_name'] . "\n";
            echo "Owner: " . $booking['owner_name'] . "\n";
            echo "---\n";
        }
    } else {
        echo "No bookings with RFID found\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
