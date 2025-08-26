<?php
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    $db = getDB();
    $dbName = $db->query('SELECT DATABASE() AS db')->fetch(PDO::FETCH_ASSOC)['db'] ?? null;
    $stmt = $db->prepare("SELECT id, email, email_verified, is_active, LEFT(password_hash,4) AS hash_prefix, CHAR_LENGTH(password_hash) AS hash_len FROM users WHERE email = ?");
    $stmt->execute(['admin@animates.ph']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['success'=>true,'database'=>$dbName,'user'=>$user]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
?>


