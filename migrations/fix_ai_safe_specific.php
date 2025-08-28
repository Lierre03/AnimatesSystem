<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/plain');

function safeRebuildAI(PDO $db, string $table): void {
    echo "\n== {$table} ==\n";
    $exists = $db->query("SHOW TABLES LIKE '{$table}'")->fetchColumn();
    if (!$exists) { echo " - Missing table\n"; return; }

    try {
        $db->beginTransaction();

        // Drop PK if exists to avoid multiple PK error
        $hasPk = (bool)$db->query("SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY'")->fetch();
        if ($hasPk) {
            echo " - Dropping existing PRIMARY KEY...\n";
            $db->exec("ALTER TABLE `{$table}` DROP PRIMARY KEY");
        }

        // Clean up any leftover temp column
        $tmp = $db->query("SHOW COLUMNS FROM `{$table}` LIKE '__tmp_ai'")->fetch(PDO::FETCH_ASSOC);
        if ($tmp) {
            echo " - Dropping leftover __tmp_ai column...\n";
            $db->exec("ALTER TABLE `{$table}` DROP COLUMN `__tmp_ai`");
        }

        // Add temporary AI PK column at the front
        echo " - Adding __tmp_ai AUTO_INCREMENT PRIMARY KEY...\n";
        $db->exec("ALTER TABLE `{$table}` ADD COLUMN `__tmp_ai` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST");

        // Drop old id column if exists
        $col = $db->query("SHOW COLUMNS FROM `{$table}` LIKE 'id'")->fetch(PDO::FETCH_ASSOC);
        if ($col) {
            echo " - Dropping old id column...\n";
            $db->exec("ALTER TABLE `{$table}` DROP COLUMN `id`");
        }

        // Rename tmp to id while keeping AI+PK
        echo " - Renaming __tmp_ai to id...\n";
        $db->exec("ALTER TABLE `{$table}` CHANGE COLUMN `__tmp_ai` `id` INT(11) NOT NULL");

        $db->commit();
        echo " - Rebuild complete\n";
    } catch (Throwable $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        echo " - Error: " . $e->getMessage() . "\n";
    }
}

try {
    $db = getDB();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    safeRebuildAI($db, 'rfid_tap_history');
    safeRebuildAI($db, 'status_updates');

    echo "\nDone.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "Error: " . $e->getMessage() . "\n";
}


