<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/plain');

$tables = ['rfid_tap_history','status_updates','rfid_cards','bookings'];

try {
    $db = getDB();
    foreach ($tables as $t) {
        $stmt = $db->query("SHOW CREATE TABLE `{$t}`");
        $row = $stmt->fetch(PDO::FETCH_NUM);
        echo "\n-- {$t} --\n";
        if ($row && isset($row[1])) {
            echo $row[1] . "\n";
        } else {
            echo "No definition found.\n";
        }
    }
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}


