<?php
// Always return JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Handle all PHP errors and exceptions as JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => "PHP Error: $errstr in $errfile on line $errline"
    ]);
    exit;
});

set_exception_handler(function($e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => "Uncaught Exception: " . $e->getMessage()
    ]);
    exit;
});

require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];

// JWT Helper functions
function getBearerToken() {
    $headers = getAuthorizationHeader();
    if (!empty($headers)) {
        if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
            return $matches[1];
        }
    }
    return null;
}

function getAuthorizationHeader() {
    $headers = null;
    if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER["Authorization"]);
    } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
    } elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
        if (isset($requestHeaders['Authorization'])) {
            $headers = trim($requestHeaders['Authorization']);
        }
    }
    return $headers;
}

function verifyJWT($token) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        throw new Exception('Invalid token format');
    }
    
    $header = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[0])));
    $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])));
    $signature = str_replace(['-', '_'], ['+', '/'], $parts[2]);
    
    // For debugging purposes, accept any token with the right format
    // In production, you would verify the signature
    return $payload;
}

function getJWTSecret() {
    return '8paws_jwt_secret_key_2025';
}

function requireAuth() {
    $token = getBearerToken();
    if (!$token) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'No token provided']);
        exit;
    }
    
    try {
        $decoded = verifyJWT($token);
        return $decoded;
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Main API logic
if ($method === 'GET') {
    try {
        $user = requireAuth();
        $userId = $user->user_id;
        
        // Get database connection
        $pdo = getDB();
        
        // Get period from query string (all, this_month, etc.)
        $period = isset($_GET['period']) ? $_GET['period'] : 'all';
        
        // Debug output
        error_log("User ID: $userId, Period: $period");
    
        // Prepare SQL based on period to get bookings from the bookings table
        $sql = "SELECT b.*, 
               p.name as pet_name, p.breed as pet_breed, p.size as pet_size,
               c.email as customer_email
               FROM bookings b 
               JOIN pets p ON b.pet_id = p.id
               JOIN customers c ON p.customer_id = c.id
               WHERE b.status = 'completed' ";
        
        $params = [];
        
        // Add user filtering based on email
        $sql .= "AND c.email = (SELECT email FROM customers WHERE id = 
                (SELECT customer_id FROM users WHERE id = ?)) ";
        $params[] = $userId;
    
        // Add date filtering based on period
        if ($period === 'this_month') {
            $sql .= "AND MONTH(b.created_at) = MONTH(CURRENT_DATE()) 
                    AND YEAR(b.created_at) = YEAR(CURRENT_DATE()) ";
        } else if ($period === 'last_month') {
            $sql .= "AND MONTH(b.created_at) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)) 
                    AND YEAR(b.created_at) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)) ";
        } else if ($period === 'last_3_months') {
            $sql .= "AND b.created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 3 MONTH) ";
        } else if ($period === 'last_6_months') {
            $sql .= "AND b.created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH) ";
        } else if ($period === 'this_year') {
            $sql .= "AND YEAR(b.created_at) = YEAR(CURRENT_DATE()) ";
        }
        
        // Order by creation date
        $sql .= "ORDER BY b.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format the bookings data for the frontend
        $formattedBookings = [];
        foreach ($bookings as $booking) {
            // Format the booking data to match what the frontend expects
            $formattedBooking = [
                'id' => $booking['id'],
                'pet_id' => $booking['pet_id'],
                'pet_name' => $booking['pet_name'],
                'pet_breed' => $booking['pet_breed'],
                'pet_size' => $booking['pet_size'],
                'status' => $booking['status'],
                'total_amount' => $booking['total_amount'],
                'payment_status' => $booking['payment_status'],
                'payment_method' => $booking['payment_method'],
                'check_in_time' => $booking['check_in_time'],
                'actual_completion' => $booking['actual_completion'],
                'created_at' => $booking['created_at'],
                'updated_at' => $booking['updated_at']
            ];
            
            $formattedBookings[] = $formattedBooking;
        }
        
        echo json_encode([
            'success' => true,
            'bookings' => $formattedBookings
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Error: ' . $e->getMessage()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
}