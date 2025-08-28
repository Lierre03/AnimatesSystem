<?php
// test_discount_storage.php - Test discount storage in sales_transactions
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/database.php';

echo "<h2>Discount Storage Test in Sales Transactions</h2>";

try {
    $db = getDB();
    
    // Test 1: Check current sales_transactions structure
    echo "<h3>Test 1: Current Sales Transactions Structure</h3>";
    
    $stmt = $db->prepare("DESCRIBE sales_transactions");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasDiscountColumn = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'discount_amount') {
            $hasDiscountColumn = true;
            echo "<p>✅ Found discount_amount column: {$column['Type']}</p>";
            break;
        }
    }
    
    if (!$hasDiscountColumn) {
        echo "<p style='color: red;'>❌ discount_amount column not found!</p>";
        exit;
    }
    
    // Test 2: Check recent transactions with discounts
    echo "<h3>Test 2: Recent Transactions with Discounts</h3>";
    
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
    
    if (empty($transactions)) {
        echo "<p style='color: orange;'>⚠️ No transactions with discounts found</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background-color: #f0f0f0;'>";
        echo "<th>ID</th><th>RFID</th><th>Customer</th><th>Pet</th><th>Original Amount</th><th>Discount</th><th>Final Amount</th><th>Payment Method</th><th>Date</th>";
        echo "</tr>";
        
        foreach ($transactions as $txn) {
            $originalAmount = $txn['total_amount'];
            $discount = $txn['discount_amount'];
            $finalAmount = $txn['amount'];
            $expectedFinal = $originalAmount - $discount;
            
            $status = ($finalAmount == $expectedFinal) ? "✅" : "❌";
            
            echo "<tr>";
            echo "<td>{$txn['id']}</td>";
            echo "<td>{$txn['custom_rfid']}</td>";
            echo "<td>{$txn['customer_name']}</td>";
            echo "<td>{$txn['pet_name']}</td>";
            echo "<td>₱{$originalAmount}</td>";
            echo "<td style='color: #28a745;'>-₱{$discount}</td>";
            echo "<td>₱{$finalAmount}</td>";
            echo "<td>{$txn['payment_method']}</td>";
            echo "<td>" . date('M j, Y g:i A', strtotime($txn['created_at'])) . "</td>";
            echo "</tr>";
            
            if ($finalAmount != $expectedFinal) {
                echo "<tr style='background-color: #ffe6e6;'>";
                echo "<td colspan='9' style='color: red;'>";
                echo "⚠️ Amount mismatch! Expected: ₱{$expectedFinal}, Stored: ₱{$finalAmount}";
                echo "</td>";
                echo "</tr>";
            }
        }
        echo "</table>";
    }
    
    // Test 3: Check all recent transactions to see discount amounts
    echo "<h3>Test 3: All Recent Transactions (Last 20)</h3>";
    
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
    ORDER BY st.created_at DESC
    LIMIT 20");
    
    $stmt->execute();
    $allTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($allTransactions)) {
        echo "<p style='color: red;'>❌ No transactions found</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background-color: #f0f0f0;'>";
        echo "<th>ID</th><th>RFID</th><th>Customer</th><th>Pet</th><th>Original Amount</th><th>Discount</th><th>Final Amount</th><th>Payment Method</th><th>Date</th>";
        echo "</tr>";
        
        foreach ($allTransactions as $txn) {
            $originalAmount = $txn['total_amount'];
            $discount = $txn['discount_amount'];
            $finalAmount = $txn['amount'];
            $expectedFinal = $originalAmount - $discount;
            
            $status = ($finalAmount == $expectedFinal) ? "✅" : "❌";
            
            $rowColor = ($discount > 0) ? "#f8fff8" : "#f9f9f9";
            
            echo "<tr style='background-color: {$rowColor};'>";
            echo "<td>{$txn['id']}</td>";
            echo "<td>{$txn['custom_rfid']}</td>";
            echo "<td>{$txn['customer_name']}</td>";
            echo "<td>{$txn['pet_name']}</td>";
            echo "<td>₱{$originalAmount}</td>";
            echo "<td>" . ($discount > 0 ? "<span style='color: #28a745;'>-₱{$discount}</span>" : "₱0.00") . "</td>";
            echo "<td>₱{$finalAmount}</td>";
            echo "<td>{$txn['payment_method']}</td>";
            echo "<td>" . date('M j, Y g:i A', strtotime($txn['created_at'])) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Test 4: Check for any NULL discount amounts
    echo "<h3>Test 4: Check for NULL Discount Amounts</h3>";
    
    $stmt = $db->prepare("SELECT COUNT(*) as null_count FROM sales_transactions WHERE discount_amount IS NULL");
    $stmt->execute();
    $nullCount = $stmt->fetch(PDO::FETCH_ASSOC)['null_count'];
    
    if ($nullCount > 0) {
        echo "<p style='color: red;'>❌ Found {$nullCount} transactions with NULL discount amounts</p>";
        
        // Show examples
        $stmt = $db->prepare("SELECT 
            st.id,
            st.booking_id,
            st.amount,
            st.discount_amount,
            b.custom_rfid,
            b.total_amount
        FROM sales_transactions st
        JOIN bookings b ON st.booking_id = b.id
        WHERE st.discount_amount IS NULL
        LIMIT 5");
        $stmt->execute();
        $nullTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p>Examples of NULL discount transactions:</p>";
        echo "<ul>";
        foreach ($nullTransactions as $txn) {
            echo "<li>ID: {$txn['id']}, RFID: {$txn['custom_rfid']}, Amount: ₱{$txn['amount']}, Original: ₱{$txn['total_amount']}</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color: green;'>✅ All transactions have proper discount amounts</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
