<?php
// test_email_with_real_discount.php - Test email receipt with real discount data
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/send_receipt.php';

echo "<h2>Email Receipt Test with Real Discount Data</h2>";

try {
    $db = getDB();
    
    // Use the RFID tag we found with a discount
    $rfid = '1BL89OOX'; // This has ₱100 discount
    
    echo "<h3>Testing with RFID: {$rfid}</h3>";
    
    // First, get the booking ID for this RFID
    $stmt = $db->prepare("SELECT id FROM bookings WHERE custom_rfid = ?");
    $stmt->execute([$rfid]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        echo "<p style='color: red;'>✗ No booking found for RFID: {$rfid}</p>";
        exit;
    }
    
    $bookingId = $booking['id'];
    echo "<p style='color: green;'>✓ Found booking ID: {$bookingId}</p>";
    
    // Check the current data in sales_transactions
    echo "<h3>Current Sales Transaction Data:</h3>";
    
    $stmt = $db->prepare("SELECT 
        st.id,
        st.amount,
        st.discount_amount,
        st.payment_method,
        b.total_amount
    FROM sales_transactions st
    JOIN bookings b ON st.booking_id = b.id
    WHERE b.custom_rfid = ?
    ORDER BY st.id DESC
    LIMIT 1");
    
    $stmt->execute([$rfid]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($transaction) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Field</th><th>Value</th><th>Status</th></tr>";
        echo "<tr><td>Original Amount</td><td>₱{$transaction['total_amount']}</td><td>✓</td></tr>";
        echo "<tr><td>Discount Applied</td><td>₱{$transaction['discount_amount']}</td><td>✓</td></tr>";
        echo "<tr><td>Final Amount (stored)</td><td>₱{$transaction['amount']}</td><td>" . 
             ($transaction['amount'] == ($transaction['total_amount'] - $transaction['discount_amount']) ? "✓ Correct" : "❌ Wrong") . "</td></tr>";
        echo "</table>";
        
        $expectedAmount = $transaction['total_amount'] - $transaction['discount_amount'];
        echo "<p><strong>Expected Final Amount:</strong> ₱{$expectedAmount}</p>";
        echo "<p><strong>Actual Stored Amount:</strong> ₱{$transaction['amount']}</p>";
        
        if ($transaction['amount'] != $expectedAmount) {
            echo "<p style='color: red;'>❌ <strong>ISSUE FOUND:</strong> The final amount in sales_transactions is incorrect!</p>";
            echo "<p>This explains why the email receipt shows the wrong total.</p>";
        }
    }
    
    // Now test the email receipt generation
    echo "<h3>Testing Email Receipt Generation:</h3>";
    
    try {
        $result = sendPaymentReceipt($bookingId, 'cash', null, null);
        if ($result) {
            echo "<p style='color: green;'>✓ Email receipt generated and sent successfully!</p>";
            echo "<p>Check your email for the receipt.</p>";
            
            // Show what the receipt should contain
            $subtotal = $transaction['total_amount'];
            $discount = $transaction['discount_amount'];
            $finalTotal = $subtotal - $discount;
            
            echo "<h4>Expected Email Receipt Content:</h4>";
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
            
        } else {
            echo "<p style='color: red;'>✗ Email receipt generation failed</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Email receipt generation error: " . $e->getMessage() . "</p>";
    }
    
    // Fix the incorrect data in sales_transactions
    echo "<h3>Fixing Incorrect Data:</h3>";
    
    if ($transaction && $transaction['amount'] != $expectedAmount) {
        echo "<p style='color: orange;'>⚠️ Fixing incorrect final amount in sales_transactions...</p>";
        
        try {
            $stmt = $db->prepare("UPDATE sales_transactions SET amount = ? WHERE id = ?");
            $stmt->execute([$expectedAmount, $transaction['id']]);
            
            echo "<p style='color: green;'>✓ Fixed sales_transactions.amount from ₱{$transaction['amount']} to ₱{$expectedAmount}</p>";
            
            // Verify the fix
            $stmt = $db->prepare("SELECT amount FROM sales_transactions WHERE id = ?");
            $stmt->execute([$transaction['id']]);
            $fixedAmount = $stmt->fetch(PDO::FETCH_COLUMN);
            
            echo "<p style='color: green;'>✓ Verified: sales_transactions.amount is now ₱{$fixedAmount}</p>";
            
        } catch (Exception $e) {
            echo "<p style='color: red;'>✗ Error fixing data: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color: green;'>✓ Data is already correct</p>";
    }
    
    echo "<h3>Test Summary</h3>";
    echo "<p>✅ Found RFID with discount: {$rfid}</p>";
    echo "<p>✅ Discount amount: ₱{$discount}</p>";
    echo "<p>✅ Expected final total: ₱{$finalTotal}</p>";
    echo "<p>✅ Email receipt should now show correct discount and total</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Test Error: " . $e->getMessage() . "</p>";
}
?>
