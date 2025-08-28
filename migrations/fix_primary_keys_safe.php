<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/plain');

function tableExists(PDO $db, string $table): bool {
    return (bool)$db->query("SHOW TABLES LIKE '{$table}'")->fetchColumn();
}

function hasPrimaryKey(PDO $db, string $table): bool {
    return (bool)$db->query("SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY'")->fetch();
}

function columnInfo(PDO $db, string $table, string $column) {
    return $db->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'")->fetch(PDO::FETCH_ASSOC);
}

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

    echo "Safely fixing PRIMARY KEY/AUTO_INCREMENT for tables...\n";

    foreach ($tablesToFix as $table) {
        echo "\n==> Table: {$table}\n";
        if (!tableExists($db, $table)) {
            echo " - Skipped (table not found)\n";
            continue;
        }

        try {
            $db->beginTransaction();

            $hasPk = hasPrimaryKey($db, $table);
            $idCol = columnInfo($db, $table, 'id');

            if (!$hasPk) {
                echo " - No PRIMARY KEY detected. Using safe swap strategy...\n";
                // Add a temporary AI PK column
                echo " - Adding temporary __tmp_ai AUTO_INCREMENT PRIMARY KEY...\n";
                $db->exec("ALTER TABLE `{$table}` ADD COLUMN `__tmp_ai` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST");

                if ($idCol) {
                    echo " - Dropping old id column...\n";
                    $db->exec("ALTER TABLE `{$table}` DROP COLUMN `id`");
                }

                echo " - Renaming __tmp_ai to id (keeps AI + PK)...\n";
                $db->exec("ALTER TABLE `{$table}` CHANGE COLUMN `__tmp_ai` `id` INT(11) NOT NULL");
            } else {
                echo " - PRIMARY KEY present. Ensuring id column and AUTO_INCREMENT...\n";
                if (!$idCol) {
                    echo " - Adding id column...\n";
                    $db->exec("ALTER TABLE `{$table}` ADD COLUMN `id` INT(11) NOT NULL FIRST");
                }

                // Ensure id is INT NOT NULL
                echo " - Ensuring id INT(11) NOT NULL...\n";
                $db->exec("ALTER TABLE `{$table}` MODIFY `id` INT(11) NOT NULL");

                // Ensure AI
                $desc = columnInfo($db, $table, 'id');
                $extra = strtolower($desc['Extra'] ?? '');
                if (strpos($extra, 'auto_increment') === false) {
                    echo " - Setting AUTO_INCREMENT on id...\n";
                    $db->exec("ALTER TABLE `{$table}` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT");
                } else {
                    echo " - AUTO_INCREMENT already set\n";
                }
            }

            $db->commit();
            echo " - Done";
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            echo " - Error on {$table}: " . $e->getMessage() . "\n";
            // Continue to next table
        }
    }

    echo "\n\nSafe fix pass completed.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "Error: " . $e->getMessage() . "\n";
}


