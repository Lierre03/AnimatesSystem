<?php
// Ensure users.role enum includes 'cashier'

require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();

    // Read current column type
    $stmt = $db->query("SHOW COLUMNS FROM `users` LIKE 'role'");
    $col = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$col) {
        throw new Exception("Column users.role not found");
    }
    $type = $col['Type']; // e.g., enum('admin','staff','customer')

    // If cashier already in enum, nothing to do
    if (stripos($type, "'cashier'") !== false) {
        echo "cashier already present in users.role enum\n";
        exit(0);
    }

    // Build new enum preserving existing order, inserting cashier after staff
    preg_match_all("/'([^']+)'/", $type, $matches);
    $values = $matches[1];
    // Guarantee base roles
    $desired = ['admin','staff','cashier','customer'];
    $newValues = [];
    foreach ($desired as $v) {
        if (!in_array($v, $newValues, true)) {
            $newValues[] = $v;
        }
    }
    $enumList = "'" . implode("','", $newValues) . "'";

    $sql = "ALTER TABLE `users` MODIFY `role` ENUM($enumList) NOT NULL DEFAULT 'staff'";
    $db->exec($sql);
    echo "Updated users.role enum to include cashier\n";
    exit(0);
} catch (Exception $e) {
    http_response_code(500);
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>


