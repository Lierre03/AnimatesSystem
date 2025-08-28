<?php
// =====================================================
// MIGRATION RUNNER: Remove 'pending' from payment status
// Date: 2025-01-28
// Description: Safely runs the migration to update payment status enums
// =====================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/database.php';

echo "<h2>Running Migration: Remove 'pending' from Payment Status</h2>";
echo "<p>This will update your existing 8paws_db database to remove the 'pending' payment status.</p>";

try {
    $db = getDB();
    
    // =====================================================
    // STEP 1: Check current state
    // =====================================================
    echo "<h3>Step 1: Checking Current Database State</h3>";
    
    // Check current payment status values in bookings
    $stmt = $db->prepare("SELECT payment_status, COUNT(*) as count FROM bookings GROUP BY payment_status");
    $stmt->execute();
    $bookingStatuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p><strong>Current payment status in bookings:</strong></p>";
    echo "<ul>";
    foreach ($bookingStatuses as $status) {
        echo "<li>{$status['payment_status']}: {$status['count']} records</li>";
    }
    echo "</ul>";
    
    // Check if billing table exists and has data
    $stmt = $db->prepare("SHOW TABLES LIKE 'billing'");
    $stmt->execute();
    $billingExists = $stmt->fetch();
    
    if ($billingExists) {
        $stmt = $db->prepare("SELECT payment_status, COUNT(*) as count FROM billing GROUP BY payment_status");
        $stmt->execute();
        $billingStatuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p><strong>Current payment status in billing:</strong></p>";
        echo "<ul>";
        foreach ($billingStatuses as $status) {
            echo "<li>{$status['payment_status']}: {$status['count']} records</li>";
        }
        echo "</ul>";
    } else {
        echo "<p><strong>Billing table does not exist yet.</strong></p>";
    }
    
    // =====================================================
    // STEP 2: Update existing records
    // =====================================================
    echo "<h3>Step 2: Updating Existing Records</h3>";
    
    // Update bookings table - change 'pending' to 'paid'
    $stmt = $db->prepare("UPDATE bookings SET payment_status = 'paid' WHERE payment_status = 'pending'");
    $stmt->execute();
    $bookingsUpdated = $stmt->rowCount();
    
    echo "<p>✅ Updated <strong>{$bookingsUpdated}</strong> booking records from 'pending' to 'paid'</p>";
    
    // Update billing table if it exists
    if ($billingExists) {
        $stmt = $db->prepare("UPDATE billing SET payment_status = 'paid' WHERE payment_status = 'pending'");
        $stmt->execute();
        $billingUpdated = $stmt->rowCount();
        
        echo "<p>✅ Updated <strong>{$billingUpdated}</strong> billing records from 'pending' to 'paid'</p>";
    }
    
    // =====================================================
    // STEP 3: Modify enum constraints
    // =====================================================
    echo "<h3>Step 3: Updating Enum Constraints</h3>";
    
    // Update bookings table enum
    try {
        $db->exec("ALTER TABLE `bookings` MODIFY COLUMN `payment_status` enum('paid','refunded') NOT NULL DEFAULT 'paid'");
        echo "<p>✅ Updated bookings.payment_status enum to ('paid','refunded')</p>";
    } catch (Exception $e) {
        echo "<p>⚠️ Warning updating bookings enum: " . $e->getMessage() . "</p>";
    }
    
    // Update billing table enum if it exists
    if ($billingExists) {
        try {
            $db->exec("ALTER TABLE `billing` MODIFY COLUMN `payment_status` enum('paid','overdue') NOT NULL DEFAULT 'paid'");
            echo "<p>✅ Updated billing.payment_status enum to ('paid','overdue')</p>";
        } catch (Exception $e) {
            echo "<p>⚠️ Warning updating billing enum: " . $e->getMessage() . "</p>";
        }
    }
    
    // =====================================================
    // STEP 4: Verify the changes
    // =====================================================
    echo "<h3>Step 4: Verifying Changes</h3>";
    
    // Check final payment status values in bookings
    $stmt = $db->prepare("SELECT payment_status, COUNT(*) as count FROM bookings GROUP BY payment_status");
    $stmt->execute();
    $finalBookingStatuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p><strong>Final payment status in bookings:</strong></p>";
    echo "<ul>";
    foreach ($finalBookingStatuses as $status) {
        echo "<li>{$status['payment_status']}: {$status['count']} records</li>";
    }
    echo "</ul>";
    
    if ($billingExists) {
        $stmt = $db->prepare("SELECT payment_status, COUNT(*) as count FROM billing GROUP BY payment_status");
        $stmt->execute();
        $finalBillingStatuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p><strong>Final payment status in billing:</strong></p>";
        echo "<ul>";
        foreach ($finalBillingStatuses as $status) {
            echo "<li>{$status['payment_status']}: {$status['count']} records</li>";
        }
        echo "</ul>";
    }
    
    // =====================================================
    // STEP 5: Check for any remaining 'pending' values
    // =====================================================
    echo "<h3>Step 5: Final Verification</h3>";
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM bookings WHERE payment_status = 'pending'");
    $stmt->execute();
    $remainingPendingBookings = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($billingExists) {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM billing WHERE payment_status = 'pending'");
        $stmt->execute();
        $remainingPendingBilling = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } else {
        $remainingPendingBilling = 0;
    }
    
    if ($remainingPendingBookings == 0 && $remainingPendingBilling == 0) {
        echo "<p style='color: green; font-weight: bold;'>✅ SUCCESS! No 'pending' payment status records remain.</p>";
    } else {
        echo "<p style='color: orange; font-weight: bold;'>⚠️ WARNING: Some 'pending' records still exist:</p>";
        echo "<ul>";
        echo "<li>Bookings: {$remainingPendingBookings}</li>";
        echo "<li>Billing: {$remainingPendingBilling}</li>";
        echo "</ul>";
    }
    
    // =====================================================
    // MIGRATION COMPLETE
    // =====================================================
    echo "<h3>🎉 Migration Summary</h3>";
    echo "<div style='background-color: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px;'>";
    echo "<p><strong>Migration completed successfully!</strong></p>";
    echo "<ul>";
    echo "<li>Updated {$bookingsUpdated} booking records</li>";
    if ($billingExists) {
        echo "<li>Updated {$billingUpdated} billing records</li>";
    }
    echo "<li>Modified enum constraints to remove 'pending'</li>";
    echo "<li>Payment status now only allows: 'paid', 'refunded' (bookings) / 'paid', 'overdue' (billing)</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<p><strong>Next steps:</strong></p>";
    echo "<ul>";
    echo "<li>Test your billing management system to ensure it works correctly</li>";
    echo "<li>Verify that new bookings are created with 'paid' status by default</li>";
    echo "<li>Check that your frontend code doesn't reference 'pending' payment status</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>❌ Migration failed: " . $e->getMessage() . "</p>";
    echo "<p>Please check the error and try again, or restore from backup if needed.</p>";
}
?>
