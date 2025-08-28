<?php
// fix_existing_discounts.php - Fix existing transactions with incorrect discount amounts
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/database.php';

echo "<h2>Fixing Existing Discount Amounts</h2>";

try {
    $db = getDB();
    
    // Find transactions with incorrect amounts
    echo "<h3>Step 1: Finding Transactions with Incorrect Amounts</h3>";
    
    $stmt = $db->prepare("SELECT 
        st.id,
        st.booking_id,
        st.amount,
        st.discount_amount,
        b.total_amount,
        b.custom_rfid,
        c.name as customer_name,
        p.name as pet_name
    FROM sales_transactions st
    JOIN bookings b ON st.booking_id = b.id
    JOIN pets p ON b.pet_id = p.id
    JOIN customers c ON p.customer_id = c.id
    WHERE st.discount_amount > 0
    ORDER BY st.created_at DESC");
    
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($transactions)) {
        echo "<p>✅ No transactions with discounts found to fix</p>";
        exit;
    }
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>ID</th><th>RFID</th><th>Customer</th><th>Pet</th><th>Original Amount</th><th>Discount</th><th>Current Final Amount</th><th>Expected Final Amount</th><th>Status</th>";
    echo "</tr>";
    
    $transactionsToFix = [];
    
    foreach ($transactions as $txn) {
        $originalAmount = $txn['total_amount'];
        $discount = $txn['discount_amount'];
        $currentFinal = $txn['amount'];
        $expectedFinal = $originalAmount - $discount;
        
        $needsFix = ($currentFinal != $expectedFinal);
        $status = $needsFix ? "❌ Needs Fix" : "✅ Correct";
        
        if ($needsFix) {
            $transactionsToFix[] = $txn;
        }
        
        echo "<tr style='background-color: " . ($needsFix ? '#ffe6e6' : '#f0fff0') . ";'>";
        echo "<td>{$txn['id']}</td>";
        echo "<td>{$txn['custom_rfid']}</td>";
        echo "<td>{$txn['customer_name']}</td>";
        echo "<td>{$txn['pet_name']}</td>";
        echo "<td>₱{$originalAmount}</td>";
        echo "<td style='color: #28a745;'>-₱{$discount}</td>";
        echo "<td>₱{$currentFinal}</td>";
        echo "<td>₱{$expectedFinal}</td>";
        echo "<td>{$status}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    if (empty($transactionsToFix)) {
        echo "<p>✅ All transactions are correct!</p>";
        exit;
    }
    
    echo "<h3>Step 2: Fixing Incorrect Transactions</h3>";
    
    // Start transaction
    $db->beginTransaction();
    
    try {
        $fixedCount = 0;
        
        foreach ($transactionsToFix as $txn) {
            $originalAmount = $txn['total_amount'];
            $discount = $txn['discount_amount'];
            $expectedFinal = $originalAmount - $discount;
            
            // Update the transaction amount
            $stmt = $db->prepare("UPDATE sales_transactions SET amount = ? WHERE id = ?");
            $stmt->execute([$expectedFinal, $txn['id']]);
            
            echo "<p>✅ Fixed Transaction ID {$txn['id']} (RFID: {$txn['custom_rfid']}):</p>";
            echo "<ul>";
            echo "<li>Original Amount: ₱{$originalAmount}</li>";
            echo "<li>Discount: ₱{$discount}</li>";
            echo "<li>Final Amount: ₱{$expectedFinal} (was ₱{$txn['amount']})</li>";
            echo "</ul>";
            
            $fixedCount++;
        }
        
        // Commit the transaction
        $db->commit();
        
        echo "<p style='color: green; font-weight: bold;'>✅ Successfully fixed {$fixedCount} transactions!</p>";
        
    } catch (Exception $e) {
        // Rollback on error
        $db->rollBack();
        echo "<p style='color: red;'>❌ Error fixing transactions: " . $e->getMessage() . "</p>";
        throw $e;
    }
    
    // Verify the fixes
    echo "<h3>Step 3: Verifying Fixes</h3>";
    
    $stmt = $db->prepare("SELECT 
        st.id,
        st.amount,
        st.discount_amount,
        b.total_amount,
        b.custom_rfid
    FROM sales_transactions st
    JOIN bookings b ON st.booking_id = b.id
    WHERE st.id IN (" . implode(',', array_column($transactionsToFix, 'id')) . ")");
    
    $stmt->execute();
    $fixedTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>ID</th><th>RFID</th><th>Original Amount</th><th>Discount</th><th>Final Amount</th><th>Status</th>";
    echo "</tr>";
    
    foreach ($fixedTransactions as $txn) {
        $originalAmount = $txn['total_amount'];
        $discount = $txn['discount_amount'];
        $finalAmount = $txn['amount'];
        $expectedFinal = $originalAmount - $discount;
        
        $isCorrect = ($finalAmount == $expectedFinal);
        $status = $isCorrect ? "✅ Fixed" : "❌ Still Wrong";
        
        echo "<tr style='background-color: " . ($isCorrect ? '#f0fff0' : '#ffe6e6') . ";'>";
        echo "<td>{$txn['id']}</td>";
        echo "<td>{$txn['custom_rfid']}</td>";
        echo "<td>₱{$originalAmount}</td>";
        echo "<td style='color: #28a745;'>-₱{$discount}</td>";
        echo "<td>₱{$finalAmount}</td>";
        echo "<td>{$status}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
