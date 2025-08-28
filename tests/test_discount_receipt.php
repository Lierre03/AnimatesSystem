<?php
// test_discount_receipt.php - Test discount receipt functionality
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/send_receipt.php';

echo "<h2>Discount Receipt Test</h2>";

try {
    $db = getDB();
    
    // Test 1: Check if sales_transactions table has discount_amount column
    echo "<h3>Test 1: Database Structure</h3>";
    
    $stmt = $db->prepare("DESCRIBE sales_transactions");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasDiscountColumn = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'discount_amount') {
            $hasDiscountColumn = true;
            break;
        }
    }
    
    if ($hasDiscountColumn) {
        echo "<p style='color: green;'>✓ discount_amount column exists</p>";
    } else {
        echo "<p style='color: red;'>✗ discount_amount column missing</p>";
        exit;
    }
    
    // Test 2: Check sample sales transactions with discounts
    echo "<h3>Test 2: Sample Sales Transactions</h3>";
    
    $stmt = $db->prepare("SELECT 
        st.id,
        st.booking_id,
        st.amount,
        st.discount_amount,
        st.payment_method,
        b.total_amount,
        b.custom_rfid
    FROM sales_transactions st
    JOIN bookings b ON st.booking_id = b.id
    WHERE st.discount_amount > 0
    ORDER BY st.id DESC
    LIMIT 5");
    
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($transactions)) {
        echo "<p>No transactions with discounts found. Creating a test transaction...</p>";
        
        // Find a recent booking to create a test transaction
        $stmt = $db->prepare("SELECT id, total_amount, custom_rfid FROM bookings ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        $testBooking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($testBooking) {
            $testDiscount = 100.00; // Test discount of ₱100
            $stmt = $db->prepare("INSERT INTO sales_transactions 
                (booking_id, transaction_reference, amount, payment_method, discount_amount, status) 
                VALUES (?, ?, ?, ?, ?, 'completed')");
            $stmt->execute([
                $testBooking['id'],
                'TEST-' . date('Ymd') . '-' . uniqid(),
                $testBooking['total_amount'],
                'cash',
                $testDiscount
            ]);
            
            echo "<p style='color: green;'>✓ Test transaction created with ₱{$testDiscount} discount</p>";
            
            // Refresh the query
            $stmt = $db->prepare("SELECT 
                st.id,
                st.booking_id,
                st.amount,
                st.discount_amount,
                st.payment_method,
                b.total_amount,
                b.custom_rfid
            FROM sales_transactions st
            JOIN bookings b ON st.booking_id = b.id
            WHERE st.discount_amount > 0
            ORDER BY st.id DESC
            LIMIT 5");
            $stmt->execute();
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    if (!empty($transactions)) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>ID</th><th>Booking ID</th><th>RFID</th><th>Original Amount</th><th>Discount</th><th>Payment Method</th></tr>";
        
        foreach ($transactions as $txn) {
            echo "<tr>";
            echo "<td>{$txn['id']}</td>";
            echo "<td>{$txn['booking_id']}</td>";
            echo "<td>{$txn['custom_rfid']}</td>";
            echo "<td>₱{$txn['total_amount']}</td>";
            echo "<td style='color: #28a745;'>₱{$txn['discount_amount']}</td>";
            echo "<td>{$txn['payment_method']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Test 3: Test receipt generation with discount
    echo "<h3>Test 3: Receipt Generation Test</h3>";
    
    if (!empty($transactions)) {
        $testTransaction = $transactions[0];
        $bookingId = $testTransaction['booking_id'];
        
        echo "<p>Testing receipt generation for booking ID: {$bookingId}</p>";
        echo "<p>Original amount: ₱{$testTransaction['total_amount']}</p>";
        echo "<p>Discount applied: ₱{$testTransaction['discount_amount']}</p>";
        
        // Test the receipt function
        try {
            $result = sendPaymentReceipt($bookingId, 'cash', null, null);
            if ($result) {
                echo "<p style='color: green;'>✓ Receipt generated successfully</p>";
                echo "<p>Check the email for the receipt with proper discount display.</p>";
            } else {
                echo "<p style='color: red;'>✗ Receipt generation failed</p>";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>✗ Receipt generation error: " . $e->getMessage() . "</p>";
        }
    }
    
    // Test 4: Verify discount calculation
    echo "<h3>Test 4: Discount Calculation Verification</h3>";
    
    if (!empty($transactions)) {
        $testTransaction = $transactions[0];
        $originalAmount = $testTransaction['total_amount'];
        $discount = $testTransaction['discount_amount'];
        $tax = round($originalAmount * 0.12);
        $finalTotal = $originalAmount - $discount + $tax;
        
        echo "<p>Original Amount: ₱{$originalAmount}</p>";
        echo "<p>Discount: -₱{$discount}</p>";
        echo "<p>Tax (12%): +₱{$tax}</p>";
        echo "<p><strong>Final Total: ₱{$finalTotal}</strong></p>";
        
        // Verify the calculation
        $expectedTotal = $originalAmount - $discount + $tax;
        if (abs($finalTotal - $expectedTotal) < 0.01) {
            echo "<p style='color: green;'>✓ Discount calculation is correct</p>";
        } else {
            echo "<p style='color: red;'>✗ Discount calculation error</p>";
        }
    }
    
    echo "<h3>Test Summary</h3>";
    echo "<p>If all tests pass, the discount system should be working properly.</p>";
    echo "<p>Check your email for the test receipt to verify the discount is displayed correctly.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Test Error: " . $e->getMessage() . "</p>";
}
?>
