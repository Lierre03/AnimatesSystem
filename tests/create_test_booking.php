<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/plain');

try {
    $db = getDB();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $customRfid = 'TVTPIV8O';

    // Ensure a customer exists
    $customerId = $db->query("SELECT id FROM customers ORDER BY id ASC LIMIT 1")->fetchColumn();
    if (!$customerId) {
        $db->exec("INSERT INTO customers (name, email) VALUES ('Test Customer', 'test@example.com')");
        $customerId = $db->lastInsertId();
    }

    // Ensure a pet exists for that customer
    $petId = $db->query("SELECT id FROM pets WHERE customer_id = " . (int)$customerId . " ORDER BY id ASC LIMIT 1")->fetchColumn();
    if (!$petId) {
        $stmt = $db->prepare("INSERT INTO pets (name, type, breed, customer_id) VALUES (?,?,?,?)");
        $stmt->execute(['Test Pet','dog','mixed',$customerId]);
        $petId = $db->lastInsertId();
    }

    // Create a booking with the custom RFID if one doesn't exist or is closed
    $stmt = $db->prepare("SELECT id, status FROM bookings WHERE custom_rfid = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$customRfid]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing && !in_array($existing['status'], ['completed','cancelled'], true)) {
        echo "Active booking already exists with id={$existing['id']} and status={$existing['status']}\n";
        exit(0);
    }

    $status = 'checked-in';
    $stmt = $db->prepare("INSERT INTO bookings (pet_id, custom_rfid, status, total_amount, created_at) VALUES (?,?,?,?, NOW())");
    $stmt->execute([$petId, $customRfid, $status, 0]);
    $bookingId = $db->lastInsertId();

    echo "Created test booking id={$bookingId} for pet_id={$petId} with custom_rfid={$customRfid} and status={$status}\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "Error: " . $e->getMessage() . "\n";
}


