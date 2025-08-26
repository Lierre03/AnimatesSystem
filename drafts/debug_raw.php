<?php
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    $email = 'admin@animates.ph';
    $password = 'Admin@1234';
    
    $db = getDB();
    $stmt = $db->prepare("SELECT id, email, password_hash FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success'=>false,'error'=>'User not found']);
        exit;
    }
    
    $storedHash = $user['password_hash'];
    $hashLen = strlen($storedHash);
    $hashPrefix = substr($storedHash, 0, 4);
    $match = password_verify($password, $storedHash);
    
    // Test with a fresh hash
    $freshHash = password_hash($password, PASSWORD_DEFAULT);
    $freshMatch = password_verify($password, $freshHash);
    
    echo json_encode([
        'success' => true,
        'email' => $email,
        'password' => $password,
        'stored_hash' => $storedHash,
        'stored_hash_len' => $hashLen,
        'stored_hash_prefix' => $hashPrefix,
        'password_match' => $match,
        'fresh_hash' => $freshHash,
        'fresh_hash_len' => strlen($freshHash),
        'fresh_match' => $freshMatch
    ]);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
?>
