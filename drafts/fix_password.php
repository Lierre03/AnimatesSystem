<?php
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    $email = 'admin@animates.ph';
    $password = 'Admin@1234';
    
    $db = getDB();
    
    // Generate proper hash
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Update the database
    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
    $stmt->execute([$hash, $email]);
    
    // Verify it worked
    $stmt = $db->prepare("SELECT password_hash FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $newHash = $user['password_hash'];
    $newLen = strlen($newHash);
    $newPrefix = substr($newHash, 0, 4);
    $match = password_verify($password, $newHash);
    
    echo json_encode([
        'success' => true,
        'message' => 'Password fixed successfully',
        'new_hash_len' => $newLen,
        'new_hash_prefix' => $newPrefix,
        'password_match' => $match,
        'hash' => $newHash
    ]);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
?>
