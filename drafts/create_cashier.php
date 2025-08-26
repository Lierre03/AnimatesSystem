<?php
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    $db = getDB();
    
    // Check if cashier already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute(['cashier@animates.ph']);
    
    if ($stmt->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'Cashier user already exists'
        ]);
        exit;
    }
    
    // Create cashier user
    $firstName = 'Cashier';
    $lastName = 'Staff';
    $email = 'cashier@animates.ph';
    $phone = '+63 912 345 6789';
    $address = 'Animates PH - Camaro Branch';
    $role = 'staff';
    $staffRole = 'cashier';
    $password = 'Cashier@1234';
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $db->prepare("
        INSERT INTO users (first_name, last_name, email, phone, address, role, staff_role, password_hash, email_verified, is_active, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1, NOW())
    ");
    
    $stmt->execute([
        $firstName,
        $lastName,
        $email,
        $phone,
        $address,
        $role,
        $staffRole,
        $passwordHash
    ]);
    
    $userId = $db->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Cashier user created successfully',
        'user' => [
            'id' => $userId,
            'email' => $email,
            'password' => $password,
            'role' => $role,
            'staff_role' => $staffRole
        ]
    ]);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
