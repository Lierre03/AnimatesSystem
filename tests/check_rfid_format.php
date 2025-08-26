<?php
require_once 'config/database.php';

try {
    $db = getDB();
    
    // Check RFID card 33 specifically
    $stmt = $db->prepare("SELECT * FROM rfid_cards WHERE id = 33");
    $stmt->execute();
    $rfidCard = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "RFID Card 33 Details:\n";
    echo "=====================\n\n";
    
    if ($rfidCard) {
        echo "ID: " . $rfidCard['id'] . "\n";
        echo "Card UID: '" . $rfidCard['card_uid'] . "' (length: " . strlen($rfidCard['card_uid']) . ")\n";
        echo "Custom UID: '" . $rfidCard['custom_uid'] . "' (length: " . strlen($rfidCard['custom_uid']) . ")\n";
        echo "Tap Count: " . $rfidCard['tap_count'] . "\n";
        echo "Status: " . $rfidCard['status'] . "\n";
        
        // Test the exact query from rfid_tap.php
        echo "\nTesting RFID Tap Query:\n";
        echo "=======================\n\n";
        
        $stmt2 = $db->prepare("
            SELECT 
                r.id as rfid_id,
                r.card_uid,
                r.custom_uid,
                r.tap_count,
                b.id as booking_id
            FROM rfid_cards r
            LEFT JOIN bookings b ON r.id = b.rfid_card_id
            WHERE (r.card_uid = ? OR r.custom_uid = ?)
            AND (b.status IS NULL OR b.status NOT IN ('completed', 'cancelled'))
            ORDER BY b.created_at DESC
            LIMIT 1
        ");
        
        // Test with card_uid
        $stmt2->execute([$rfidCard['card_uid'], $rfidCard['card_uid']]);
        $result1 = $stmt2->fetch(PDO::FETCH_ASSOC);
        echo "Query with card_uid '" . $rfidCard['card_uid'] . "': " . ($result1 ? "FOUND" : "NOT FOUND") . "\n";
        
        // Test with custom_uid
        $stmt2->execute([$rfidCard['custom_uid'], $rfidCard['custom_uid']]);
        $result2 = $stmt2->fetch(PDO::FETCH_ASSOC);
        echo "Query with custom_uid '" . $rfidCard['custom_uid'] . "': " . ($result2 ? "FOUND" : "NOT FOUND") . "\n";
        
    } else {
        echo "RFID card 33 not found\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
