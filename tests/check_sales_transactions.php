<?php
// check_sales_transactions.php - Check sales_transactions table structure
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/database.php';

echo "<h2>Sales Transactions Table Structure Check</h2>";

try {
    $db = getDB();
    
    // Check if sales_transactions table exists
    $stmt = $db->prepare("SHOW TABLES LIKE 'sales_transactions'");
    $stmt->execute();
    $tableExists = $stmt->fetch();
    
    if (!$tableExists) {
        echo "<p style='color: red;'>Table 'sales_transactions' does not exist!</p>";
        echo "<p>Creating table...</p>";
        
        $createTable = "CREATE TABLE sales_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            booking_id INT NOT NULL,
            transaction_reference VARCHAR(50) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            payment_method VARCHAR(50) NOT NULL,
            payment_platform VARCHAR(50),
            discount_amount DECIMAL(10,2) DEFAULT 0.00,
            status VARCHAR(20) DEFAULT 'completed',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (booking_id) REFERENCES bookings(id)
        )";
        
        $db->exec($createTable);
        echo "<p style='color: green;'>Table 'sales_transactions' created successfully!</p>";
    }
    
    // Check table structure
    echo "<h3>Table: sales_transactions</h3>";
    
    try {
        $stmt = $db->prepare("DESCRIBE sales_transactions");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table border='1' style='border-collapse: collapse; margin-bottom: 20px;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Default']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Extra']) . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        
        // Check if discount_amount column exists
        $hasDiscountColumn = false;
        foreach ($columns as $column) {
            if ($column['Field'] === 'discount_amount') {
                $hasDiscountColumn = true;
                break;
            }
        }
        
        if (!$hasDiscountColumn) {
            echo "<p style='color: red;'>Column 'discount_amount' is missing!</p>";
            echo "<p>Adding column...</p>";
            
            $addColumn = "ALTER TABLE sales_transactions ADD COLUMN discount_amount DECIMAL(10,2) DEFAULT 0.00";
            $db->exec($addColumn);
            echo "<p style='color: green;'>Column 'discount_amount' added successfully!</p>";
        } else {
            echo "<p style='color: green;'>Column 'discount_amount' exists ✓</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error checking table sales_transactions: " . $e->getMessage() . "</p>";
    }
    
    // Check sample data
    echo "<h3>Sample Sales Transactions Data</h3>";
    
    try {
        $stmt = $db->prepare("SELECT * FROM sales_transactions ORDER BY id DESC LIMIT 5");
        $stmt->execute();
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($transactions)) {
            echo "<p>No sales transactions found.</p>";
        } else {
            echo "<table border='1' style='border-collapse: collapse; margin-bottom: 20px;'>";
            echo "<tr>";
            foreach (array_keys($transactions[0]) as $header) {
                echo "<th>" . htmlspecialchars($header) . "</th>";
            }
            echo "</tr>";
            
            foreach ($transactions as $transaction) {
                echo "<tr>";
                foreach ($transaction as $value) {
                    echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error fetching sample data: " . $e->getMessage() . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Database connection error: " . $e->getMessage() . "</p>";
}

echo "<h3>Next Steps</h3>";
echo "<p>1. If the table or column was missing and has been created, the receipt system should now work properly.</p>";
echo "<p>2. Test the receipt generation with a new payment to verify discounts are displayed correctly.</p>";
?>
