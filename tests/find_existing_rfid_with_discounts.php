<?php
// find_existing_rfid_with_discounts.php - Find existing RFID tags with discounts
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/database.php';

echo "<h2>Finding Existing RFID Tags with Discounts</h2>";

try {
    $db = getDB();
    
    // Find all RFID tags that have discounts
    echo "<h3>RFID Tags with Discounts Applied</h3>";
    
    $stmt = $db->prepare("SELECT DISTINCT
        b.custom_rfid,
        b.total_amount,
        c.name as customer_name,
        p.name as pet_name,
        st.amount as final_amount,
        st.discount_amount,
        st.created_at
    FROM sales_transactions st
    JOIN bookings b ON st.booking_id = b.id
    JOIN pets p ON b.pet_id = p.id
    JOIN customers c ON p.customer_id = c.id
    WHERE st.discount_amount > 0
    ORDER BY st.created_at DESC");
    
    $stmt->execute();
    $discountTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($discountTransactions)) {
        echo "<p style='color: orange;'>⚠️ No transactions with discounts found</p>";
        exit;
    }
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>RFID Tag</th><th>Customer</th><th>Pet</th><th>Original Amount</th><th>Discount</th><th>Final Amount</th><th>Date</th>";
    echo "</tr>";
    
    foreach ($discountTransactions as $txn) {
        $originalAmount = $txn['total_amount'];
        $discount = $txn['discount_amount'];
        $finalAmount = $txn['final_amount'];
        $expectedFinal = $originalAmount - $discount;
        
        $rowColor = ($finalAmount == $expectedFinal) ? "#f0fff0" : "#ffe6e6";
        $status = ($finalAmount == $expectedFinal) ? "✅" : "❌";
        
        echo "<tr style='background-color: {$rowColor};'>";
        echo "<td><strong>{$txn['custom_rfid']}</strong></td>";
        echo "<td>{$txn['customer_name']}</td>";
        echo "<td>{$txn['pet_name']}</td>";
        echo "<td>₱{$originalAmount}</td>";
        echo "<td style='color: #28a745;'>-₱{$discount}</td>";
        echo "<td>₱{$finalAmount}</td>";
        echo "<td>" . date('M j, Y g:i A', strtotime($txn['created_at'])) . "</td>";
        echo "</tr>";
        
        if ($finalAmount != $expectedFinal) {
            echo "<tr style='background-color: #ffe6e6;'>";
            echo "<td colspan='7' style='color: red;'>";
            echo "⚠️ Amount mismatch! Expected: ₱{$expectedFinal}, Stored: ₱{$finalAmount}";
            echo "</td>";
            echo "</tr>";
        }
    }
    echo "</table>";
    
    // Show summary
    echo "<h3>Summary</h3>";
    echo "<p>Found <strong>" . count($discountTransactions) . "</strong> RFID tags with discounts applied.</p>";
    
    // Find RFID tags that might be similar to I4OPS)V5
    echo "<h3>RFID Tags Similar to I4OPS)V5</h3>";
    
    $stmt = $db->prepare("SELECT 
        custom_rfid,
        total_amount,
        status,
        payment_status
    FROM bookings 
    WHERE custom_rfid LIKE '%I4OPS%' 
    OR custom_rfid LIKE '%V5%'
    OR custom_rfid LIKE '%1400%'
    ORDER BY check_in_time DESC");
    
    $stmt->execute();
    $similarRFIDs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($similarRFIDs)) {
        echo "<p>No RFID tags found similar to 'I4OPS)V5'</p>";
    } else {
        echo "<p>Found similar RFID tags:</p>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>RFID</th><th>Amount</th><th>Status</th><th>Payment</th></tr>";
        foreach ($similarRFIDs as $rfid) {
            echo "<tr>";
            echo "<td><strong>{$rfid['custom_rfid']}</strong></td>";
            echo "<td>₱{$rfid['total_amount']}</td>";
            echo "<td>{$rfid['status']}</td>";
            echo "<td>{$rfid['payment_status']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Show all recent RFID tags for reference
    echo "<h3>Recent RFID Tags (Last 20)</h3>";
    
    $stmt = $db->prepare("SELECT 
        b.custom_rfid,
        b.total_amount,
        b.status,
        b.payment_status,
        b.check_in_time,
        c.name as customer_name,
        p.name as pet_name
    FROM bookings b
    JOIN pets p ON b.pet_id = p.id
    JOIN customers c ON p.customer_id = c.id
    ORDER BY b.check_in_time DESC
    LIMIT 20");
    
    $stmt->execute();
    $recentRFIDs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>RFID Tag</th><th>Customer</th><th>Pet</th><th>Amount</th><th>Status</th><th>Payment</th><th>Check-in</th>";
    echo "</tr>";
    
    foreach ($recentRFIDs as $rfid) {
        $rowColor = ($rfid['total_amount'] == 1400) ? "#fff3cd" : "#f9f9f9";
        $highlight = ($rfid['total_amount'] == 1400) ? " (₱1400)" : "";
        
        echo "<tr style='background-color: {$rowColor};'>";
        echo "<td><strong>{$rfid['custom_rfid']}{$highlight}</strong></td>";
        echo "<td>{$rfid['customer_name']}</td>";
        echo "<td>{$rfid['pet_name']}</td>";
        echo "<td>₱{$rfid['total_amount']}</td>";
        echo "<td>{$rfid['status']}</td>";
        echo "<td>{$rfid['payment_status']}</td>";
        echo "<td>" . date('M j, Y g:i A', strtotime($rfid['check_in_time'])) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p style='color: green; font-weight: bold;'>✅ Search completed! Check the results above.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
