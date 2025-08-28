<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/plain');

try {
    $db = getDB();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Starting users.id fixer on current database...\n";

    // Ensure users table exists
    $exists = $db->query("SHOW TABLES LIKE 'users'")->fetch();
    if (!$exists) {
        throw new Exception("Table 'users' not found in current database");
    }

    // Count rows with id = 0
    $zeroCount = (int)$db->query("SELECT COUNT(*) FROM users WHERE id = 0")->fetchColumn();
    $maxId = (int)$db->query("SELECT IFNULL(MAX(id),0) FROM users")->fetchColumn();
    echo "Rows with id=0: {$zeroCount}\n";
    echo "Current MAX(id): {$maxId}\n";

    if ($zeroCount > 0) {
        $db->beginTransaction();
        // Fetch affected rows' rowids (emulate by selecting pk-less rows)
        $stmt = $db->query("SELECT email FROM users WHERE id = 0");
        $toFix = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $nextId = $maxId;
        $upd = $db->prepare("UPDATE users SET id = ? WHERE email = ? AND id = 0");
        foreach ($toFix as $email) {
            $nextId += 1;
            $upd->execute([$nextId, $email]);
        }
        $db->commit();
        echo "Updated {$zeroCount} rows to new IDs up to {$nextId}.\n";
    } else {
        echo "No rows with id=0 need fixing.\n";
    }

    // Add PK if missing
    $colInfo = $db->query("SHOW KEYS FROM users WHERE Key_name = 'PRIMARY'")->fetch();
    if (!$colInfo) {
        echo "Adding PRIMARY KEY on users.id...\n";
        $db->exec("ALTER TABLE `users` MODIFY `id` INT(11) NOT NULL");
        $db->exec("ALTER TABLE `users` ADD PRIMARY KEY (`id`)");
    } else {
        echo "PRIMARY KEY already present.\n";
    }

    // Ensure AUTO_INCREMENT
    $desc = $db->query("SHOW COLUMNS FROM users LIKE 'id'")->fetch(PDO::FETCH_ASSOC);
    $type = strtolower($desc['Type'] ?? '');
    $extra = strtolower($desc['Extra'] ?? '');
    if (strpos($extra, 'auto_increment') === false) {
        echo "Setting AUTO_INCREMENT on users.id...\n";
        $db->exec("ALTER TABLE `users` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT");
    } else {
        echo "AUTO_INCREMENT already set.\n";
    }

    echo "Done.\n";
} catch (Exception $e) {
    http_response_code(500);
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}


