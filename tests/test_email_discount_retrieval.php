<?php
// test_email_discount_retrieval.php - Test email receipt discount retrieval
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/database.php';

echo "<h2>Testing Email Receipt Discount Retrieval</h2>";

try {
    $db = getDB();
    
    // Test 1: Check the exact query used in send_receipt.php
    echo "<h3>Test 1: Simulating Email Receipt Query</h3>";
    
    // Use the exact same query as in send_receipt.php
    $stmt = $db->prepare("SELECT 
        b.id as booking_id,
        b.custom_rfid,
        b.total_amount,
        b.check_in_time,
        b.amount_tendered,
        b.change_amount,
        
        p.name as pet_name,
        p.type as pet_type,
        p.breed as pet_breed,
        c.name as owner_name,
        c.phone as owner_phone,
        c.email as owner_email,
        st.amount as sales_transaction_amount,
        COALESCE(st.discount_amount, 0) as sales_transaction_discount_amount
    FROM bookings b
    JOIN pets p ON b.pet_id = p.id
    JOIN customers c ON p.customer_id = c.id
    LEFT JOIN (
        SELECT 
            st1.booking_id,
            st1.amount,
            st1.discount_amount
        FROM sales_transactions st1
        INNER JOIN (
            SELECT booking_id, MAX(id) as max_id
            FROM sales_transactions
            GROUP BY booking_id
        ) st2 ON st1.booking_id = st2.booking_id AND st1.id = st2.max_id
    ) st ON b.id = st.booking_id
    WHERE b.id = ?");
    
    // Test with a specific booking that has a discount
    $testBookingId = 104; // RFID: 1BL89OOX which has ₱100 discount
    
    $stmt->execute([$testBookingId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        echo "<p style='color: red;'>❌ No booking found for ID: $testBookingId</p>";
        exit;
    }
    
    echo "<p>✅ Found booking:</p>";
    echo "<ul>";
    echo "<li><strong>Booking ID:</strong> {$booking['booking_id']}</li>";
    echo "<li><strong>RFID:</strong> {$booking['custom_rfid']}</li>";
    echo "<li><strong>Customer:</strong> {$booking['owner_name']}</li>";
    echo "<li><strong>Pet:</strong> {$booking['pet_name']} ({$booking['pet_type']} - {$booking['pet_breed']})</li>";
    echo "<li><strong>Original Amount:</strong> ₱{$booking['total_amount']}</li>";
    echo "<li><strong>Sales Transaction Amount:</strong> ₱{$booking['sales_transaction_amount']}</li>";
    echo "<li><strong>Discount Amount:</strong> ₱{$booking['sales_transaction_discount_amount']}</li>";
    echo "</ul>";
    
    // Test 2: Calculate what the email receipt should show
    echo "<h3>Test 2: Email Receipt Calculation</h3>";
    
    $subtotal = $booking['total_amount'];
    $discount = floatval($booking['sales_transaction_discount_amount'] ?? 0);
    $finalTotal = $subtotal - $discount;
    
    echo "<div style='border: 1px solid #ddd; padding: 20px; margin: 20px 0; background-color: #f9f9f9;'>";
    echo "<h4>Email Receipt Calculation for RFID: {$booking['custom_rfid']}</h4>";
    echo "<p><strong>Subtotal:</strong> ₱{$subtotal}</p>";
    echo "<p><strong>Discount Applied:</strong> -₱{$discount}</p>";
    echo "<p><strong>Final Total:</strong> ₱{$finalTotal}</p>";
    echo "</div>";
    
    // Test 3: Check if the discount is being retrieved correctly
    echo "<h3>Test 3: Discount Retrieval Verification</h3>";
    
    if ($discount > 0) {
        echo "<p style='color: green;'>✅ Discount is being retrieved: ₱{$discount}</p>";
        
        if ($finalTotal == ($subtotal - $discount)) {
            echo "<p style='color: green;'>✅ Final total calculation is correct: ₱{$finalTotal}</p>";
        } else {
            echo "<p style='color: red;'>❌ Final total calculation is wrong!</p>";
            echo "<p>Expected: ₱" . ($subtotal - $discount) . ", Got: ₱{$finalTotal}</p>";
        }
    } else {
        echo "<p style='color: orange;'>⚠️ No discount found in this booking</p>";
    }
    
    // Test 4: Check the raw sales_transactions data
    echo "<h3>Test 4: Raw Sales Transactions Data</h3>";
    
    $stmt = $db->prepare("SELECT 
        id,
        booking_id,
        amount,
        discount_amount,
        payment_method,
        created_at
    FROM sales_transactions 
    WHERE booking_id = ?
    ORDER BY id DESC");
    
    $stmt->execute([$testBookingId]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($transactions)) {
        echo "<p style='color: red;'>❌ No sales transactions found for this booking</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background-color: #f0f0f0;'>";
        echo "<th>ID</th><th>Amount</th><th>Discount</th><th>Payment Method</th><th>Date</th>";
        echo "</tr>";
        
        foreach ($transactions as $txn) {
            $rowColor = ($txn['id'] == max(array_column($transactions, 'id'))) ? "#f0fff0" : "#f9f9f9";
            $isLatest = ($txn['id'] == max(array_column($transactions, 'id'))) ? " (LATEST)" : "";
            
            echo "<tr style='background-color: {$rowColor};'>";
            echo "<td>{$txn['id']}{$isLatest}</td>";
            echo "<td>₱{$txn['amount']}</td>";
            echo "<td>₱{$txn['discount_amount']}</td>";
            echo "<td>{$txn['payment_method']}</td>";
            echo "<td>" . date('M j, Y g:i A', strtotime($txn['created_at'])) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Show which transaction the email receipt should use
        $latestTransaction = $transactions[0];
        echo "<p><strong>Latest Transaction (ID: {$latestTransaction['id']}):</strong></p>";
        echo "<ul>";
        echo "<li>Amount: ₱{$latestTransaction['amount']}</li>";
        echo "<li>Discount: ₱{$latestTransaction['discount_amount']}</li>";
        echo "<li>Payment Method: {$latestTransaction['payment_method']}</li>";
        echo "</ul>";
    }
    
    // Test 5: Verify the subquery logic
    echo "<h3>Test 5: Subquery Logic Verification</h3>";
    
    $stmt = $db->prepare("SELECT 
        st1.booking_id,
        st1.amount,
        st1.discount_amount
    FROM sales_transactions st1
    INNER JOIN (
        SELECT booking_id, MAX(id) as max_id
        FROM sales_transactions
        GROUP BY booking_id
    ) st2 ON st1.booking_id = st2.booking_id AND st1.id = st2.max_id
    WHERE st1.booking_id = ?");
    
    $stmt->execute([$testBookingId]);
    $latestTransaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($latestTransaction) {
        echo "<p style='color: green;'>✅ Subquery found latest transaction:</p>";
        echo "<ul>";
        echo "<li>Amount: ₱{$latestTransaction['amount']}</li>";
        echo "<li>Discount: ₱{$latestTransaction['discount_amount']}</li>";
        echo "</ul>";
        
        if ($latestTransaction['discount_amount'] == $discount) {
            echo "<p style='color: green;'>✅ Discount amounts match!</p>";
        } else {
            echo "<p style='color: red;'>❌ Discount amounts don't match!</p>";
            echo "<p>Subquery: ₱{$latestTransaction['discount_amount']}, Email Query: ₱{$discount}</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Subquery failed to find latest transaction</p>";
    }
    
    echo "<p style='color: green; font-weight: bold;'>✅ Test completed! Check the results above.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>

