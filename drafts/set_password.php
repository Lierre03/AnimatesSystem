<?php
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    $email = isset($_GET['email']) ? trim($_GET['email']) : 'admin@animates.ph';
    $password = isset($_GET['password']) ? $_GET['password'] : '';
    if ($email === '' || $password === '') {
        http_response_code(400);
        echo json_encode(['success'=>false,'error'=>'Provide email and password query params']);
        exit;
    }
    $db = getDB();
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("UPDATE users SET password_hash = ?, email_verified = 1, is_active = 1 WHERE email = ?");
    $stmt->execute([$hash, $email]);
    echo json_encode(['success'=>true,'email'=>$email,'hash_prefix'=>substr($hash,0,4),'hash_len'=>strlen($hash)]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
?>


