<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/plain');

try {
    $db = getDB();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $targets = ['rfid_cards','rfid_tap_history','status_updates'];
    foreach ($targets as $t) {
        echo "\n== {$t} ==\n";
        $exists = $db->query("SHOW TABLES LIKE '{$t}'")->fetchColumn();
        if (!$exists) { echo " - Missing table\n"; continue; }

        $col = $db->query("SHOW COLUMNS FROM `{$t}` LIKE 'id'")->fetch(PDO::FETCH_ASSOC);
        if (!$col) {
            echo " - Adding id column...\n";
            $db->exec("ALTER TABLE `{$t}` ADD COLUMN `id` INT(11) NOT NULL");
        }

        // Ensure PRIMARY KEY exists
        $hasPk = (bool)$db->query("SHOW KEYS FROM `{$t}` WHERE Key_name = 'PRIMARY'")->fetch();
        if (!$hasPk) {
            echo " - Adding PRIMARY KEY(id)...\n";
            $db->exec("ALTER TABLE `{$t}` ADD PRIMARY KEY (`id`)");
        }

        // Set AUTO_INCREMENT
        echo " - Setting id to AUTO_INCREMENT...\n";
        $db->exec("ALTER TABLE `{$t}` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT");
        echo " - Done\n";
    }

    echo "\nCompleted.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "Error: " . $e->getMessage() . "\n";
}


