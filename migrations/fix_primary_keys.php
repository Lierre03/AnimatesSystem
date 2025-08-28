<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/plain');

try {
    $db = getDB();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $tablesToFix = [
        'app_config',
        'appointment_services',
        'appointments',
        'booking_services',
        'customers',
        'pet_sizes',
        'pets',
        'rfid_cards',
        'rfid_tags',
        'rfid_tap_history',
        'sales_transactions',
        'service_pricing',
        'services',
        'services2',
        'status_updates',
        'user_pets',
    ];

    echo "Fixing PRIMARY KEY/AUTO_INCREMENT for core tables...\n";

    foreach ($tablesToFix as $table) {
        echo "\n==> Table: {$table}\n";

        // Does table exist?
        $exists = $db->query("SHOW TABLES LIKE '{$table}'")->fetchColumn();
        if (!$exists) {
            echo " - Skipped (table not found)\n";
            continue;
        }

        // Check existing id column
        $col = $db->query("SHOW COLUMNS FROM `{$table}` LIKE 'id'")->fetch(PDO::FETCH_ASSOC);
        if (!$col) {
            echo " - Adding id column...\n";
            $db->exec("ALTER TABLE `{$table}` ADD COLUMN `id` INT(11) NOT NULL");
        }

        // Ensure id is NOT NULL INT(11)
        echo " - Ensuring id INT(11) NOT NULL...\n";
        $db->exec("ALTER TABLE `{$table}` MODIFY `id` INT(11) NOT NULL");

        // Add PRIMARY KEY if missing
        $hasPrimary = (bool)$db->query("SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY'")->fetch();
        if (!$hasPrimary) {
            echo " - Adding PRIMARY KEY(id)...\n";
            $db->exec("ALTER TABLE `{$table}` ADD PRIMARY KEY (`id`)");
        } else {
            echo " - PRIMARY KEY already present\n";
        }

        // Set AUTO_INCREMENT on id
        $desc = $db->query("SHOW COLUMNS FROM `{$table}` LIKE 'id'")->fetch(PDO::FETCH_ASSOC);
        $extra = strtolower($desc['Extra'] ?? '');
        if (strpos($extra, 'auto_increment') === false) {
            echo " - Setting AUTO_INCREMENT on id...\n";
            $db->exec("ALTER TABLE `{$table}` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT");
        } else {
            echo " - AUTO_INCREMENT already set\n";
        }

        echo " - Done";
    }

    echo "\n\nAll requested fixes attempted.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "Error: " . $e->getMessage() . "\n";
}


