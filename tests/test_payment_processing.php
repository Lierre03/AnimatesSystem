<?php
require_once __DIR__ . '/../config/database.php';

// Test database connection
try {
    $db = getDB();
    echo "Database connection successful\n";
    
    // Test if the new columns exist
    $stmt = $db->prepare("DESCRIBE bookings");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('amount_tendered', $columns)) {
        echo "amount_tendered column exists\n";
    } else {
        echo "amount_tendered column missing\n";
    }
    
    if (in_array('change_amount', $columns)) {
        echo "change_amount column exists\n";
    } else {
        echo "change_amount column missing\n";
    }
    
    // Test a simple payment update
    $testData = [
        'booking_id' => 1,
        'payment_method' => 'cash',
        'payment_reference' => null,
        'payment_platform' => null,
        'amount_tendered' => 1000.00,
        'change_amount' => 100.00
    ];
    
    $stmt = $db->prepare("UPDATE bookings SET 
        status = 'completed', 
        payment_status = 'paid',
        actual_completion = NOW(),
        payment_method = ?,
        payment_reference = ?,
        payment_platform = ?,
        amount_tendered = ?,
        change_amount = ?,
        payment_date = NOW()
        WHERE id = ?");
    
    $result = $stmt->execute([
        $testData['payment_method'],
        $testData['payment_reference'],
        $testData['payment_platform'],
        $testData['amount_tendered'],
        $testData['change_amount'],
        $testData['booking_id']
    ]);
    
    if ($result) {
        echo "Payment update test successful\n";
    } else {
        echo "Payment update test failed\n";
        print_r($stmt->errorInfo());
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
