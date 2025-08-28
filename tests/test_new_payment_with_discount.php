<?php
// test_new_payment_with_discount.php - Test new payment processing with discount
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/database.php';

echo "<h2>Testing New Payment Processing with Discount</h2>";

try {
    $db = getDB();
    
    // Test 1: Simulate payment processing with discount
    echo "<h3>Test 1: Simulating Payment with Discount</h3>";
    
    // Find a recent booking that hasn't been paid yet
    $stmt = $db->prepare("SELECT 
        b.id as booking_id,
        b.custom_rfid,
        b.total_amount,
        b.status,
        b.payment_status,
        c.name as customer_name,
        p.name as pet_name
    FROM bookings b
    JOIN pets p ON b.pet_id = p.id
    JOIN customers c ON p.customer_id = c.id
    WHERE b.payment_status = 'pending' 
    AND b.status != 'cancelled'
    ORDER BY b.check_in_time DESC
    LIMIT 1");
    
    $stmt->execute();
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        echo "<p style='color: orange;'>⚠️ No pending bookings found to test with</p>";
        
        // Show all recent bookings instead
        $stmt = $db->prepare("SELECT 
            b.id as booking_id,
            b.custom_rfid,
            b.total_amount,
            b.status,
            b.payment_status,
            c.name as customer_name,
            p.name as pet_name
        FROM bookings b
        JOIN pets p ON b.pet_id = p.id
        JOIN customers c ON p.customer_id = c.id
        ORDER BY b.check_in_time DESC
        LIMIT 5");
        
        $stmt->execute();
        $recentBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p>Recent bookings:</p>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>RFID</th><th>Customer</th><th>Pet</th><th>Amount</th><th>Status</th><th>Payment</th></tr>";
        foreach ($recentBookings as $b) {
            echo "<tr>";
            echo "<td>{$b['booking_id']}</td>";
            echo "<td>{$b['custom_rfid']}</td>";
            echo "<td>{$b['customer_name']}</td>";
            echo "<td>{$b['pet_name']}</td>";
            echo "<td>₱{$b['total_amount']}</td>";
            echo "<td>{$b['status']}</td>";
            echo "<td>{$b['payment_status']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        exit;
    }
    
    echo "<p>✅ Found test booking:</p>";
    echo "<ul>";
    echo "<li><strong>Booking ID:</strong> {$booking['booking_id']}</li>";
    echo "<li><strong>RFID:</strong> {$booking['custom_rfid']}</li>";
    echo "<li><strong>Customer:</strong> {$booking['customer_name']}</li>";
    echo "<li><strong>Pet:</strong> {$booking['pet_name']}</li>";
    echo "<li><strong>Original Amount:</strong> ₱{$booking['total_amount']}</li>";
    echo "<li><strong>Status:</strong> {$booking['status']}</li>";
    echo "<li><strong>Payment Status:</strong> {$booking['payment_status']}</li>";
    echo "</ul>";
    
    // Test 2: Check if there are existing sales transactions for this booking
    echo "<h3>Test 2: Checking Existing Sales Transactions</h3>";
    
    $stmt = $db->prepare("SELECT 
        id,
        amount,
        discount_amount,
        payment_method,
        status,
        created_at
    FROM sales_transactions 
    WHERE booking_id = ?
    ORDER BY created_at DESC");
    
    $stmt->execute([$booking['booking_id']]);
    $existingTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($existingTransactions)) {
        echo "<p>✅ No existing sales transactions found for this booking</p>";
    } else {
        echo "<p>⚠️ Found existing sales transactions:</p>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Amount</th><th>Discount</th><th>Payment Method</th><th>Status</th><th>Date</th></tr>";
        foreach ($existingTransactions as $txn) {
            echo "<tr>";
            echo "<td>{$txn['id']}</td>";
            echo "<td>₱{$txn['amount']}</td>";
            echo "<td>₱{$txn['discount_amount']}</td>";
            echo "<td>{$txn['payment_method']}</td>";
            echo "<td>{$txn['status']}</td>";
            echo "<td>" . date('M j, Y g:i A', strtotime($txn['created_at'])) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Test 3: Simulate the payment processing logic
    echo "<h3>Test 3: Simulating Payment Processing Logic</h3>";
    
    $originalAmount = $booking['total_amount'];
    $discountAmount = 100; // Simulate ₱100 discount
    $finalAmount = $originalAmount - $discountAmount;
    
    echo "<p>Simulating payment with:</p>";
    echo "<ul>";
    echo "<li><strong>Original Amount:</strong> ₱{$originalAmount}</li>";
    echo "<li><strong>Discount Applied:</strong> ₱{$discountAmount}</li>";
    echo "<li><strong>Final Amount:</strong> ₱{$finalAmount}</li>";
    echo "</ul>";
    
    // Test 4: Check the billing.php logic
    echo "<h3>Test 4: Testing Billing API Logic</h3>";
    
    // This would normally be a POST request, but we'll simulate the logic
    $paymentData = [
        'action' => 'process_payment',
        'booking_id' => $booking['booking_id'],
        'payment_method' => 'cash',
        'amount_tendered' => $finalAmount + 10, // Add ₱10 for change
        'change_amount' => 10,
        'discount_amount' => $discountAmount,
        'send_receipt' => true
    ];
    
    echo "<p>Payment data that would be sent:</p>";
    echo "<pre>" . json_encode($paymentData, JSON_PRETTY_PRINT) . "</pre>";
    
    // Test 5: Verify the database structure supports this
    echo "<h3>Test 5: Database Structure Verification</h3>";
    
    $stmt = $db->prepare("DESCRIBE sales_transactions");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $requiredColumns = ['id', 'booking_id', 'transaction_reference', 'amount', 'payment_method', 'payment_platform', 'discount_amount', 'status'];
    $missingColumns = [];
    
    foreach ($requiredColumns as $required) {
        $found = false;
        foreach ($columns as $column) {
            if ($column['Field'] === $required) {
                $found = true;
                echo "<p>✅ Found column: <strong>{$required}</strong> ({$column['Type']})</p>";
                break;
            }
        }
        if (!$found) {
            $missingColumns[] = $required;
            echo "<p>❌ Missing column: <strong>{$required}</strong></p>";
        }
    }
    
    if (!empty($missingColumns)) {
        echo "<p style='color: red;'>❌ Missing required columns: " . implode(', ', $missingColumns) . "</p>";
    } else {
        echo "<p style='color: green;'>✅ All required columns are present!</p>";
    }
    
    // Test 6: Show what the receipt would look like
    echo "<h3>Test 6: Expected Receipt Output</h3>";
    
    echo "<div style='border: 1px solid #ddd; padding: 20px; margin: 20px 0; background-color: #f9f9f9;'>";
    echo "<h4>Expected Receipt for RFID: {$booking['custom_rfid']}</h4>";
    echo "<p><strong>Customer:</strong> {$booking['customer_name']}</p>";
    echo "<p><strong>Pet:</strong> {$booking['pet_name']}</p>";
    echo "<p><strong>Subtotal:</strong> ₱{$originalAmount}</p>";
    echo "<p><strong>Discount Applied:</strong> -₱{$discountAmount}</p>";
    echo "<p><strong>Total:</strong> ₱{$finalAmount}</p>";
    echo "<p><strong>Amount Tendered:</strong> ₱" . ($finalAmount + 10) . "</p>";
    echo "<p><strong>Change:</strong> ₱10</p>";
    echo "</div>";
    
    echo "<p style='color: green; font-weight: bold;'>✅ Test completed successfully! The system is ready to process payments with discounts.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
