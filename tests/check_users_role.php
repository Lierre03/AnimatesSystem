<?php
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

try {
    $db = getDB();

    // Check column existence
    $stmt = $db->query("SHOW COLUMNS FROM `users`");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasRole = false;
    foreach ($columns as $col) {
        if (strcasecmp($col['Field'], 'role') === 0) { $hasRole = true; break; }
    }

    // Count users total
    $total = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();

    // Group by role if present
    $byRole = [];
    if ($hasRole) {
        $stmt = $db->query("SELECT role, COUNT(*) as cnt FROM users GROUP BY role ORDER BY cnt DESC");
        $byRole = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Sample recent users
    $sample = [];
    $stmt = $db->query("SELECT id, full_name, email, role, is_active, username FROM users ORDER BY id DESC LIMIT 10");
    $sample = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'has_role_column' => $hasRole,
        'total_users' => $total,
        'by_role' => $byRole,
        'sample' => $sample,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}


