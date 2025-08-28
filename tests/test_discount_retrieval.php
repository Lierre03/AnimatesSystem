<?php
// test_discount_retrieval.php - Test discount retrieval for email receipts
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/database.php';

echo "<h2>Discount Retrieval Test for Email Receipts</h2>";

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
    LEFT JOIN sales_transactions st ON b.id = st.booking_id
    WHERE b.custom_rfid = ?");
    
    $rfid = 'I4OPS)V5'; // Your example RFID
    $stmt->execute([$rfid]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($booking) {
        echo "<p style='color: green;'>✓ Booking found for RFID: {$rfid}</p>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Field</th><th>Value</th></tr>";
        echo "<tr><td>Booking ID</td><td>{$booking['booking_id']}</td></tr>";
        echo "<tr><td>RFID</td><td>{$booking['custom_rfid']}</td></tr>";
        echo "<tr><td>Total Amount</td><td>₱{$booking['total_amount']}</td></tr>";
        echo "<tr><td>Amount Tendered</td><td>₱" . ($booking['amount_tendered'] ?? 'N/A') . "</td></tr>";
        echo "<tr><td>Change Amount</td><td>₱" . ($booking['change_amount'] ?? 'N/A') . "</td></tr>";
        echo "<tr><td>Sales Transaction Amount</td><td>₱" . ($booking['sales_transaction_amount'] ?? 'N/A') . "</td></tr>";
        echo "<tr><td>Discount Amount</td><td style='color: #28a745;'>₱{$booking['sales_transaction_discount_amount']}</td></tr>";
        echo "</table>";
        
        // Calculate what the email receipt should show
        $subtotal = $booking['total_amount'];
        $discount = floatval($booking['sales_transaction_discount_amount'] ?? 0);
        $finalTotal = $subtotal - $discount;
        
        echo "<h4>Email Receipt Calculation:</h4>";
        echo "<ul>";
        echo "<li>Subtotal: ₱{$subtotal}</li>";
        echo "<li>Discount: ₱{$discount}</li>";
        echo "<li>Final Total: ₱{$finalTotal}</li>";
        echo "</ul>";
        
        if ($discount > 0) {
            echo "<p style='color: green;'>✓ Discount found: ₱{$discount}</p>";
        } else {
            echo "<p style='color: red;'>✗ No discount found in sales_transactions</p>";
        }
        
    } else {
        echo "<p style='color: red;'>✗ No booking found for RFID: {$rfid}</p>";
    }
    
    // Test 2: Check sales_transactions directly
    echo "<h3>Test 2: Direct Sales Transactions Check</h3>";
    
    $stmt = $db->prepare("SELECT 
        st.id,
        st.booking_id,
        st.amount,
        st.discount_amount,
        st.payment_method,
        st.created_at,
        b.custom_rfid,
        b.total_amount
    FROM sales_transactions st
    JOIN bookings b ON st.booking_id = b.id
    WHERE b.custom_rfid = ?
    ORDER BY st.id DESC");
    
    $stmt->execute([$rfid]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($transactions)) {
        echo "<p style='color: green;'>✓ Found " . count($transactions) . " transaction(s)</p>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>ID</th><th>Booking ID</th><th>RFID</th><th>Original Amount</th><th>Final Amount</th><th>Discount</th><th>Payment Method</th><th>Created</th></tr>";
        
        foreach ($transactions as $txn) {
            echo "<tr>";
            echo "<td>{$txn['id']}</td>";
            echo "<td>{$txn['booking_id']}</td>";
            echo "<td>{$txn['custom_rfid']}</td>";
            echo "<td>₱{$txn['total_amount']}</td>";
            echo "<td>₱{$txn['amount']}</td>";
            echo "<td style='color: #28a745;'>₱{$txn['discount_amount']}</td>";
            echo "<td>{$txn['payment_method']}</td>";
            echo "<td>{$txn['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check if the discount calculation is correct
        $latestTransaction = $transactions[0];
        $originalAmount = $latestTransaction['total_amount'];
        $finalAmount = $latestTransaction['amount'];
        $discountAmount = $latestTransaction['discount_amount'];
        
        $expectedFinalAmount = $originalAmount - $discountAmount;
        
        echo "<h4>Transaction Verification:</h4>";
        echo "<ul>";
        echo "<li>Original Amount: ₱{$originalAmount}</li>";
        echo "<li>Discount Applied: ₱{$discountAmount}</li>";
        echo "<li>Expected Final Amount: ₱{$expectedFinalAmount}</li>";
        echo "<li>Actual Final Amount: ₱{$finalAmount}</li>";
        echo "</ul>";
        
        if (abs($expectedFinalAmount - $finalAmount) < 0.01) {
            echo "<p style='color: green;'>✓ Transaction calculation is correct</p>";
        } else {
            echo "<p style='color: red;'>✗ Transaction calculation mismatch!</p>";
        }
        
    } else {
        echo "<p style='color: red;'>✗ No transactions found for RFID: {$rfid}</p>";
    }
    
    // Test 3: Check if there's a mismatch between what's stored and what's retrieved
    echo "<h3>Test 3: Data Consistency Check</h3>";
    
    if ($booking && !empty($transactions)) {
        $emailDiscount = $booking['sales_transaction_discount_amount'];
        $directDiscount = $transactions[0]['discount_amount'];
        
        echo "<p><strong>Email Receipt Query Result:</strong> ₱{$emailDiscount}</p>";
        echo "<p><strong>Direct Transaction Query Result:</strong> ₱{$directDiscount}</p>";
        
        if ($emailDiscount == $directDiscount) {
            echo "<p style='color: green;'>✓ Data consistency: Email and direct query return same discount</p>";
        } else {
            echo "<p style='color: red;'>✗ Data inconsistency: Email query returns ₱{$emailDiscount}, Direct query returns ₱{$directDiscount}</p>";
        }
    }
    
    // Test 4: Simulate the exact email receipt generation
    echo "<h3>Test 4: Email Receipt Generation Simulation</h3>";
    
    if ($booking) {
        $subtotal = $booking['total_amount'];
        $discount = floatval($booking['sales_transaction_discount_amount'] ?? 0);
        $finalTotal = $subtotal - $discount;
        
        echo "<p><strong>Email Receipt Content:</strong></p>";
        echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
        
        if ($discount > 0) {
            echo "<div style='background-color: #f8fff8; border: 2px solid #28a745; border-radius: 8px; padding: 15px; margin: 20px 0; text-align: center;'>";
            echo "<h3 style='color: #28a745; margin: 0 0 10px 0;'>🎉 Discount Applied!</h3>";
            echo "<p style='font-size: 18px; margin: 0; color: #333;'>";
            echo "You saved <strong>₱{$discount}</strong> on this transaction!";
            echo "</p>";
            echo "</div>";
        }
        
        echo "<div class='total'>";
        echo "<div style='margin-bottom: 10px;'>";
        echo "<span>Subtotal: ₱{$subtotal}</span>";
        echo "</div>";
        
        if ($discount > 0) {
            echo "<div style='margin-bottom: 10px; color: #28a745;'>";
            echo "<span>Discount Applied: -₱{$discount}</span>";
            echo "</div>";
        }
        
        echo "<div style='font-size: 20px;'>";
        echo "<span>Total: ₱{$finalTotal}</span>";
        echo "</div>";
        echo "</div>";
        echo "</div>";
        
        echo "<p><strong>Expected vs Actual:</strong></p>";
        echo "<ul>";
        echo "<li>Expected Total (with discount): ₱{$finalTotal}</li>";
        echo "<li>Original Amount: ₱{$subtotal}</li>";
        echo "<li>Discount Applied: ₱{$discount}</li>";
        echo "</ul>";
    }
    
    echo "<h3>Test Summary</h3>";
    if ($booking && !empty($transactions)) {
        echo "<p>✅ Booking found and transactions exist</p>";
        echo "<p>✅ Discount amount: ₱{$discount}</p>";
        echo "<p>✅ Final total should be: ₱{$finalTotal}</p>";
        echo "<p>✅ Email receipt should match print receipt exactly</p>";
    } else {
        echo "<p style='color: red;'>❌ Issue detected - check the data flow</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Test Error: " . $e->getMessage() . "</p>";
}
?>
