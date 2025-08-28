<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/plain');

try {
    $db = getDB();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $dbName = $db->query('SELECT DATABASE()')->fetchColumn();
    echo "Scanning database: {$dbName}\n\n";

    $tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    if (!$tables) {
        echo "No tables found.\n";
        exit;
    }

    $issues = [];
    foreach ($tables as $table) {
        $hasPrimary = (bool)$db->query("SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY'")->fetch();
        $col = $db->query("SHOW COLUMNS FROM `{$table}` LIKE 'id'")->fetch(PDO::FETCH_ASSOC);
        $auto = $col ? (strpos(strtolower($col['Extra'] ?? ''), 'auto_increment') !== false) : false;

        $problem = [];
        if (!$hasPrimary) { $problem[] = 'missing PRIMARY KEY'; }
        if (!$col) { $problem[] = "missing 'id' column"; }
        elseif (!$auto) { $problem[] = "'id' not AUTO_INCREMENT"; }

        if ($problem) {
            $issues[$table] = $problem;
        }
    }

    if (empty($issues)) {
        echo "All tables have PRIMARY KEY and AUTO_INCREMENT id.\n";
        exit;
    }

    echo "Tables with issues:\n";
    foreach ($issues as $table => $problem) {
        echo "- {$table}: " . implode(', ', $problem) . "\n";
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo "Error: " . $e->getMessage() . "\n";
}


