<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/plain');

$tables = [
    'rfid_tap_history',
    'status_updates',
    'rfid_cards',
    'bookings',
];

try {
    $db = getDB();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    foreach ($tables as $t) {
        echo "\n== {$t} ==\n";
        $col = $db->query("SHOW COLUMNS FROM `{$t}` LIKE 'id'")->fetch(PDO::FETCH_ASSOC);
        if (!$col) { echo " - No id column found\n"; continue; }
        echo " - Extra: " . ($col['Extra'] ?? '') . "\n";
        $countZero = (int)$db->query("SELECT COUNT(*) FROM `{$t}` WHERE id = 0")->fetchColumn();
        echo " - Rows with id=0: {$countZero}\n";
        $maxId = (int)$db->query("SELECT IFNULL(MAX(id),0) FROM `{$t}`")->fetchColumn();
        echo " - MAX(id): {$maxId}\n";
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo "Error: " . $e->getMessage() . "\n";
}


