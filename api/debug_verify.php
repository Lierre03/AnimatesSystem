<?php
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    $email = isset($_GET['email']) ? trim($_GET['email']) : '';
    $password = isset($_GET['password']) ? $_GET['password'] : '';
    if ($email === '' || $password === '') {
        http_response_code(400);
        echo json_encode(['success'=>false,'error'=>'email and password required as query params']);
        exit;
    }
    $db = getDB();
    $stmt = $db->prepare("SELECT id, email, password_hash, email_verified, is_active FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        echo json_encode(['success'=>true,'found'=>false]);
        exit;
    }
    $hashLen = strlen($user['password_hash']);
    $hashPrefix = substr($user['password_hash'], 0, 4);
    $match = password_verify($password, $user['password_hash']);
    echo json_encode([
        'success'=>true,
        'found'=>true,
        'email_verified'=>(int)$user['email_verified'],
        'is_active'=>(int)$user['is_active'],
        'hash_prefix'=>$hashPrefix,
        'hash_len'=>$hashLen,
        'password_match'=>$match
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
?>


