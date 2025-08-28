<?php
// find_discount_transactions.php - Find actual RFID tags with discounts
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/database.php';

echo "<h2>Finding RFID Tags with Discounts</h2>";

try {
    $db = getDB();
    
    // Find all transactions with discounts
    $stmt = $db->prepare("SELECT 
        st.id,
        st.booking_id,
        st.amount,
        st.discount_amount,
        st.payment_method,
        st.created_at,
        b.custom_rfid,
        b.total_amount,
        c.name as customer_name,
        p.name as pet_name
    FROM sales_transactions st
    JOIN bookings b ON st.booking_id = b.id
    JOIN pets p ON b.pet_id = p.id
    JOIN customers c ON p.customer_id = c.id
    WHERE st.discount_amount > 0
    ORDER BY st.created_at DESC
    LIMIT 10");
    
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($transactions)) {
        echo "<p style='color: green;'>✓ Found " . count($transactions) . " transactions with discounts</p>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>ID</th><th>RFID</th><th>Customer</th><th>Pet</th><th>Original Amount</th><th>Final Amount</th><th>Discount</th><th>Payment Method</th><th>Created</th></tr>";
        
        foreach ($transactions as $txn) {
            echo "<tr>";
            echo "<td>{$txn['id']}</td>";
            echo "<td><strong>{$txn['custom_rfid']}</strong></td>";
            echo "<td>{$txn['customer_name']}</td>";
            echo "<td>{$txn['pet_name']}</td>";
            echo "<td>₱{$txn['total_amount']}</td>";
            echo "<td>₱{$txn['amount']}</td>";
            echo "<td style='color: #28a745; font-weight: bold;'>₱{$txn['discount_amount']}</td>";
            echo "<td>{$txn['payment_method']}</td>";
            echo "<td>{$txn['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Show the first one as an example
        $example = $transactions[0];
        echo "<h3>Example Transaction for Testing:</h3>";
        echo "<p><strong>RFID:</strong> {$example['custom_rfid']}</p>";
        echo "<p><strong>Original Amount:</strong> ₱{$example['total_amount']}</p>";
        echo "<p><strong>Discount Applied:</strong> ₱{$example['discount_amount']}</p>";
        echo "<p><strong>Final Amount:</strong> ₱{$example['amount']}</p>";
        echo "<p><strong>Customer:</strong> {$example['customer_name']}</p>";
        echo "<p><strong>Pet:</strong> {$example['pet_name']}</p>";
        
    } else {
        echo "<p style='color: red;'>✗ No transactions with discounts found</p>";
        
        // Check if there are any transactions at all
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM sales_transactions");
        $stmt->execute();
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        echo "<p>Total transactions in database: {$total}</p>";
        
        if ($total > 0) {
            // Show some recent transactions without discounts
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
            ORDER BY st.created_at DESC
            LIMIT 5");
            
            $stmt->execute();
            $recentTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<h3>Recent Transactions (No Discounts):</h3>";
            echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
            echo "<tr><th>ID</th><th>RFID</th><th>Original Amount</th><th>Final Amount</th><th>Discount</th><th>Payment Method</th></tr>";
            
            foreach ($recentTransactions as $txn) {
                echo "<tr>";
                echo "<td>{$txn['id']}</td>";
                echo "<td><strong>{$txn['custom_rfid']}</strong></td>";
                echo "<td>₱{$txn['total_amount']}</td>";
                echo "<td>₱{$txn['amount']}</td>";
                echo "<td>₱{$txn['discount_amount']}</td>";
                echo "<td>{$txn['payment_method']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }
    
    // Check if there are any pending payments that could be used for testing
    echo "<h3>Pending Payments for Testing:</h3>";
    
    $stmt = $db->prepare("SELECT 
        b.id,
        b.custom_rfid,
        b.total_amount,
        c.name as customer_name,
        p.name as pet_name
    FROM bookings b
    JOIN pets p ON b.pet_id = p.id
    JOIN customers c ON p.customer_id = c.id
    WHERE b.payment_status = 'pending'
    ORDER BY b.check_in_time DESC
    LIMIT 5");
    
    $stmt->execute();
    $pendingBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($pendingBookings)) {
        echo "<p style='color: green;'>✓ Found " . count($pendingBookings) . " pending payments</p>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>ID</th><th>RFID</th><th>Customer</th><th>Pet</th><th>Amount</th></tr>";
        
        foreach ($pendingBookings as $booking) {
            echo "<tr>";
            echo "<td>{$booking['id']}</td>";
            echo "<td><strong>{$booking['custom_rfid']}</strong></td>";
            echo "<td>{$booking['customer_name']}</td>";
            echo "<td>{$booking['pet_name']}</td>";
            echo "<td>₱{$booking['total_amount']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<p><strong>You can use any of these RFID tags to test the discount system:</strong></p>";
        foreach ($pendingBookings as $booking) {
            echo "<p>• <strong>{$booking['custom_rfid']}</strong> - ₱{$booking['total_amount']} ({$booking['customer_name']}'s {$booking['pet_name']})</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ No pending payments found</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Test Error: " . $e->getMessage() . "</p>";
}
?>
