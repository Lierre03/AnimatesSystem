<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/plain');

function fixTable(PDO $db, string $table): void {
    echo "\n== {$table} ==\n";
    $exists = $db->query("SHOW TABLES LIKE '{$table}'")->fetchColumn();
    if (!$exists) { echo " - Missing table\n"; return; }

    // Ensure PRIMARY KEY(id)
    $hasPk = (bool)$db->query("SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY'")->fetch();
    if (!$hasPk) {
        echo " - Adding PRIMARY KEY(id)...\n";
        $db->exec("ALTER TABLE `{$table}` ADD PRIMARY KEY (`id`)");
    }

    // Reassign id=0 rows to unique ids
    $zeroCount = (int)$db->query("SELECT COUNT(*) FROM `{$table}` WHERE id = 0")->fetchColumn();
    $maxId = (int)$db->query("SELECT IFNULL(MAX(id),0) FROM `{$table}`")->fetchColumn();
    echo " - Rows with id=0: {$zeroCount}, MAX(id): {$maxId}\n";
    if ($zeroCount > 0) {
        $stmt = $db->query("SELECT ROW_NUMBER() OVER () AS rn FROM `{$table}` WHERE id = 0");
        // Fallback for MariaDB older: emulate row_number by fetching all and manual counter
        $ids = $db->query("SELECT id FROM `{$table}` WHERE id = 0")->fetchAll(PDO::FETCH_COLUMN);
        $upd = $db->prepare("UPDATE `{$table}` SET id = ? WHERE id = 0 LIMIT 1");
        $next = $maxId;
        foreach ($ids as $_) {
            $next++;
            $upd->execute([$next]);
        }
        echo " - Reassigned {$zeroCount} rows up to id={$next}\n";
    }

    // Set AUTO_INCREMENT
    echo " - Setting AUTO_INCREMENT on id...\n";
    $db->exec("ALTER TABLE `{$table}` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT");
    echo " - Done\n";
}

try {
    $db = getDB();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    foreach (['rfid_tap_history','status_updates'] as $t) {
        fixTable($db, $t);
    }

    echo "\nCompleted.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "Error: " . $e->getMessage() . "\n";
}


