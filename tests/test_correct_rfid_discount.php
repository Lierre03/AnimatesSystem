<?php
// test_correct_rfid_discount.php - Test discount retrieval for RFID I4OPS0V5
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/database.php';

echo "<h2>Testing Discount Retrieval for RFID: I4OPS0V5</h2>";

try {
    $db = getDB();
    
    // Test 1: Find the booking for RFID I4OPS0V5
    echo "<h3>Test 1: Finding Booking for RFID I4OPS0V5</h3>";
    
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
    WHERE b.custom_rfid = ?");
    
    $stmt->execute(['I4OPS0V5']);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        echo "<p style='color: red;'>❌ No booking found for RFID: I4OPS0V5</p>";
        exit;
    }
    
    echo "<p>✅ Found booking:</p>";
    echo "<ul>";
    echo "<li><strong>Booking ID:</strong> {$booking['booking_id']}</li>";
    echo "<li><strong>RFID:</strong> {$booking['custom_rfid']}</li>";
    echo "<li><strong>Customer:</strong> {$booking['customer_name']}</li>";
    echo "<li><strong>Pet:</strong> {$booking['pet_name']}</li>";
    echo "<li><strong>Original Amount:</strong> ₱{$booking['total_amount']}</li>";
    echo "<li><strong>Status:</strong> {$booking['status']}</li>";
    echo "<li><strong>Payment Status:</strong> {$booking['payment_status']}</li>";
    echo "</ul>";
    
    // Test 2: Check sales transactions for this booking
    echo "<h3>Test 2: Sales Transactions for I4OPS0V5</h3>";
    
    $stmt = $db->prepare("SELECT 
        id,
        amount,
        discount_amount,
        payment_method,
        created_at
    FROM sales_transactions 
    WHERE booking_id = ?
    ORDER BY id DESC");
    
    $stmt->execute([$booking['booking_id']]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($transactions)) {
        echo "<p style='color: orange;'>⚠️ No sales transactions found for this booking</p>";
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
        
        // Show the latest transaction details
        $latestTransaction = $transactions[0];
        echo "<p><strong>Latest Transaction:</strong></p>";
        echo "<ul>";
        echo "<li>Amount: ₱{$latestTransaction['amount']}</li>";
        echo "<li>Discount: ₱{$latestTransaction['discount_amount']}</li>";
        echo "<li>Payment Method: {$latestTransaction['payment_method']}</li>";
        echo "</ul>";
    }
    
    // Test 3: Simulate the email receipt query for this RFID
    echo "<h3>Test 3: Email Receipt Query Simulation</h3>";
    
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
    
    $stmt->execute([$booking['booking_id']]);
    $emailReceiptData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($emailReceiptData) {
        echo "<p>✅ Email receipt query results:</p>";
        echo "<ul>";
        echo "<li><strong>Original Amount:</strong> ₱{$emailReceiptData['total_amount']}</li>";
        echo "<li><strong>Sales Transaction Amount:</strong> ₱{$emailReceiptData['sales_transaction_amount']}</li>";
        echo "<li><strong>Discount Amount:</strong> ₱{$emailReceiptData['sales_transaction_discount_amount']}</li>";
        echo "</ul>";
        
        // Calculate what the email receipt should show
        $subtotal = $emailReceiptData['total_amount'];
        $discount = floatval($emailReceiptData['sales_transaction_discount_amount'] ?? 0);
        $finalTotal = $subtotal - $discount;
        
        echo "<div style='border: 1px solid #ddd; padding: 20px; margin: 20px 0; background-color: #f9f9f9;'>";
        echo "<h4>Expected Email Receipt for RFID: {$emailReceiptData['custom_rfid']}</h4>";
        echo "<p><strong>Subtotal:</strong> ₱{$subtotal}</p>";
        echo "<p><strong>Discount Applied:</strong> -₱{$discount}</p>";
        echo "<p><strong>Final Total:</strong> ₱{$finalTotal}</p>";
        echo "</div>";
        
        if ($discount > 0) {
            echo "<p style='color: green;'>✅ Discount is being retrieved correctly!</p>";
            echo "<p>This means the email receipt should now show:</p>";
            echo "<ul>";
            echo "<li>Subtotal: ₱{$subtotal}</li>";
            echo "<li>Discount Applied: -₱{$discount}</li>";
            echo "<li>Total: ₱{$finalTotal}</li>";
            echo "</ul>";
        } else {
            echo "<p style='color: orange;'>⚠️ No discount found - this would cause the email to show ₱1400</p>";
        }
    }
    
    // Test 4: Verify the discount calculation
    echo "<h3>Test 4: Discount Calculation Verification</h3>";
    
    $originalAmount = $booking['total_amount']; // ₱1400
    $discountAmount = $latestTransaction['discount_amount']; // ₱210
    $finalAmount = $latestTransaction['amount']; // ₱1190
    $expectedFinal = $originalAmount - $discountAmount; // ₱1400 - ₱210 = ₱1190
    
    echo "<p>Discount calculation verification:</p>";
    echo "<ul>";
    echo "<li><strong>Original Amount:</strong> ₱{$originalAmount}</li>";
    echo "<li><strong>Discount Applied:</strong> ₱{$discountAmount}</li>";
    echo "<li><strong>Expected Final Amount:</strong> ₱{$expectedFinal}</li>";
    echo "<li><strong>Actual Final Amount:</strong> ₱{$finalAmount}</li>";
    echo "<li><strong>Calculation Correct:</strong> " . ($finalAmount == $expectedFinal ? "✅ Yes" : "❌ No") . "</li>";
    echo "</ul>";
    
    if ($finalAmount == $expectedFinal) {
        echo "<p style='color: green; font-weight: bold;'>✅ Perfect! The discount calculation is correct.</p>";
        echo "<p>The email receipt should now show the correct discounted amount (₱1190) instead of the original amount (₱1400).</p>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>❌ There's still an issue with the discount calculation.</p>";
    }
    
    echo "<p style='color: green; font-weight: bold;'>✅ Test completed! Check the results above.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>

