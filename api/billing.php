<?php
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'print_receipt') {
    // For print receipt action, set content type to HTML
    header('Content-Type: text/html');
} else {
    // For all other actions, set content type to JSON
    header('Content-Type: application/json');
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';
require_once '../includes/send_receipt.php';

try {
    $db = getDB();
    
    // Handle POST request for processing payment
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (isset($data['action']) && $data['action'] === 'process_payment') {
            $bookingId = $data['booking_id'] ?? 0;
            $paymentMethod = $data['payment_method'] ?? '';
            $paymentReference = $data['payment_reference'] ?? null;
            $paymentPlatform = $data['payment_platform'] ?? null;
            $sendReceipt = $data['send_receipt'] ?? false;
            
            if (empty($bookingId) || empty($paymentMethod)) {
                echo json_encode(['success' => false, 'message' => 'Booking ID and payment method are required']);
                exit;
            }
            
            // Update booking status to completed and payment status to paid
            $stmt = $db->prepare("UPDATE bookings SET 
                status = 'completed', 
                payment_status = 'paid',
                actual_completion = NOW(),
                payment_method = ?,
                payment_reference = ?,
                payment_platform = ?,
                payment_date = NOW()
                WHERE id = ?");
            $stmt->execute([$paymentMethod, $paymentReference, $paymentPlatform, $bookingId]);
            
            $receiptSent = false;
            
            // Send receipt if requested
            if ($sendReceipt) {
                try {
                    $receiptSent = sendPaymentReceipt($bookingId, $paymentMethod, $paymentReference, $paymentPlatform);
                } catch (Exception $e) {
                    error_log("Error sending receipt: " . $e->getMessage());
                    $receiptSent = false;
                }
            }
            
            echo json_encode([
                'success' => true, 
                'message' => 'Payment processed successfully', 
                'receipt_sent' => $receiptSent
            ]);
            exit;
        }
    }
    
    // Handle GET request for printing receipt
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'print_receipt') {
        $bookingId = $_GET['booking_id'] ?? 0;
        $paymentMethod = $_GET['payment_method'] ?? '';
        $paymentReference = $_GET['payment_reference'] ?? null;
        $paymentPlatform = $_GET['payment_platform'] ?? null;
        
        if (empty($bookingId)) {
            echo "<p>Error: Booking ID is required</p>";
            exit;
        }
        
        // Get booking details
        $stmt = $db->prepare("SELECT 
            b.id as booking_id,
            b.custom_rfid,
            b.total_amount,
            b.check_in_time,
            p.name as pet_name,
            p.type as pet_type,
            p.breed as pet_breed,
            c.name as owner_name,
            c.phone as owner_phone,
            c.email as owner_email,
            GROUP_CONCAT(CONCAT(s.name, ' - ₱', s.price) SEPARATOR '<br>') as services
        FROM bookings b
        JOIN pets p ON b.pet_id = p.id
        JOIN customers c ON p.customer_id = c.id
        LEFT JOIN booking_services bs ON b.id = bs.booking_id
        LEFT JOIN services s ON bs.service_id = s.id
        WHERE b.id = ?
        GROUP BY b.id");
        
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$booking) {
            echo "<p>Error: Booking not found</p>";
            exit;
        }
        
        // Format date
        $date = new DateTime($booking['check_in_time']);
        $formattedDate = $date->format('F j, Y');
        $formattedTime = $date->format('h:i A');
        
        // Generate receipt number
        $receiptNumber = 'RCPT-' . date('Ymd') . '-' . $booking['booking_id'];
        
        // Format payment info
        $paymentInfo = $paymentMethod;
        if ($paymentMethod === 'online') {
            $paymentInfo .= " ($paymentPlatform, Ref: $paymentReference)";
        }
        
        // Output printable receipt HTML
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Animates PH - Receipt #$receiptNumber</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    margin: 0;
                    padding: 20px;
                }
                .receipt {
                    max-width: 800px;
                    margin: 0 auto;
                    border: 1px solid #ddd;
                    padding: 20px;
                }
                .header {
                    text-align: center;
                    margin-bottom: 20px;
                    border-bottom: 2px solid #D4AF37;
                    padding-bottom: 10px;
                }
                .receipt-details table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .receipt-details td {
                    padding: 8px;
                    border-bottom: 1px solid #eee;
                }
                .services {
                    margin: 20px 0;
                    border-top: 1px solid #eee;
                    border-bottom: 1px solid #eee;
                    padding: 10px 0;
                }
                .total {
                    font-size: 18px;
                    font-weight: bold;
                    text-align: right;
                    margin-top: 20px;
                    border-top: 2px solid #D4AF37;
                    padding-top: 10px;
                }
                .thank-you {
                    text-align: center;
                    font-size: 18px;
                    margin: 30px 0;
                    color: #D4AF37;
                }
                .footer {
                    margin-top: 30px;
                    font-size: 12px;
                    text-align: center;
                    color: #777;
                }
                @media print {
                    body {
                        padding: 0;
                        margin: 0;
                    }
                    .receipt {
                        border: none;
                        width: 100%;
                        max-width: 100%;
                    }
                    .no-print {
                        display: none;
                    }
                }
            </style>
        </head>
        <body>
            <div class=\"receipt\">
                <div class=\"header\">
                    <h2>Animates PH - Payment Receipt</h2>
                    <p>Camaro Branch</p>
                </div>
                
                <div class=\"receipt-details\">
                    <table>
                        <tr>
                            <td><strong>Receipt #:</strong></td>
                            <td>$receiptNumber</td>
                        </tr>
                        <tr>
                            <td><strong>Date:</strong></td>
                            <td>$formattedDate</td>
                        </tr>
                        <tr>
                            <td><strong>Time:</strong></td>
                            <td>$formattedTime</td>
                        </tr>
                        <tr>
                            <td><strong>Customer:</strong></td>
                            <td>{$booking['owner_name']}</td>
                        </tr>
                        <tr>
                            <td><strong>Pet:</strong></td>
                            <td>{$booking['pet_name']} ({$booking['pet_type']} - {$booking['pet_breed']})</td>
                        </tr>
                        <tr>
                            <td><strong>RFID:</strong></td>
                            <td>{$booking['custom_rfid']}</td>
                        </tr>
                        <tr>
                            <td><strong>Payment Method:</strong></td>
                            <td>$paymentInfo</td>
                        </tr>
                    </table>
                </div>
                
                <div class=\"services\">
                    <h3>Services</h3>
                    {$booking['services']}
                </div>
                
                <div class=\"total\">
                    Total: ₱{$booking['total_amount']}
                </div>
                
                <div class=\"thank-you\">
                    Thank you for choosing Animates PH!
                </div>
                
                <div class=\"footer\">
                    <p>© 2025 Animates PH. All rights reserved.</p>
                </div>
                
                <div class=\"no-print\" style=\"text-align: center; margin-top: 30px;\">
                    <button onclick=\"window.print()\" style=\"padding: 10px 20px; background: #D4AF37; color: white; border: none; border-radius: 5px; cursor: pointer;\">
                        Print Receipt
                    </button>
                </div>
            </div>
        </body>
        </html>";
        exit;
    }
    
    // Handle regular GET request for billing information
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $rfid = $_GET['rfid'] ?? '';
        
        if (empty($rfid)) {
            echo json_encode(['success' => false, 'error' => 'RFID tag is required']);
            exit;
        }
        
        // Query to get booking information with pet and customer details
        $query = "
            SELECT 
                b.id as booking_id,
                b.custom_rfid,
                b.total_amount,
                b.status,
                b.payment_status,
                b.check_in_time,
                b.estimated_completion,
                b.actual_completion,
                b.staff_notes,
                p.id as pet_id,
                p.name as pet_name,
                p.breed as pet_breed,
                p.type as pet_type,
                p.size as pet_size,
                p.special_notes as pet_notes,
                c.id as customer_id,
                c.name as owner_name,
                c.phone as owner_phone,
                c.email as owner_email,
                c.address as owner_address
            FROM bookings b
            LEFT JOIN pets p ON b.pet_id = p.id
            LEFT JOIN customers c ON p.customer_id = c.id
            WHERE b.custom_rfid = ?
            ORDER BY b.check_in_time DESC
            LIMIT 1
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$rfid]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$booking) {
            echo json_encode(['success' => false, 'error' => 'No booking found for RFID tag: ' . $rfid]);
            exit;
        }
        
        // Calculate service timeline
        $checkinTime = new DateTime($booking['check_in_time']);
        $estimatedTime = $booking['estimated_completion'] ? new DateTime($booking['estimated_completion']) : null;
        $actualTime = $booking['actual_completion'] ? new DateTime($booking['actual_completion']) : null;
        
        // Calculate duration
        $duration = '';
        if ($actualTime) {
            $diff = $checkinTime->diff($actualTime);
            $duration = $diff->format('%hh %im');
        } elseif ($estimatedTime) {
            $diff = $checkinTime->diff($estimatedTime);
            $duration = $diff->format('%hh %im') . ' (estimated)';
        }
        
        // Generate service breakdown based on pet type and size
        $services = generateServiceBreakdown($booking['pet_type'], $booking['pet_size'], $booking['total_amount']);
        
        // Format response
        $response = [
            'success' => true,
            'pet' => [
                'petName' => $booking['pet_name'] ?? 'Unknown Pet',
                'breed' => $booking['pet_breed'] ?? 'Unknown Breed',
                'owner' => $booking['owner_name'] ?? 'Unknown Owner',
                'phone' => $booking['owner_phone'] ?? 'No phone',
                'email' => $booking['owner_email'] ?? 'No email',
                'checkinTime' => $checkinTime->format('g:i A'),
                'bathTime' => $checkinTime->format('g:i A'), // Assuming bath starts immediately
                'groomingTime' => $estimatedTime ? $estimatedTime->format('g:i A') : 'TBD',
                'staff' => 'Staff Member', // You can add staff assignment logic here
                'services' => $services,
                'status' => $booking['status'],
                'paymentStatus' => $booking['payment_status'] ?? 'pending',
                'duration' => $duration,
                'totalAmount' => $booking['total_amount'],
                'rfidTag' => $booking['custom_rfid'],
                'bookingId' => $booking['booking_id']
            ]
        ];
        
        echo json_encode($response);
        
    } else {
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
    
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

// Helper function to generate service breakdown based on pet type and size
function generateServiceBreakdown($petType, $petSize, $totalAmount) {
    $services = [];
    
    // Base services for different pet types
    if ($petType === 'dog') {
        $services[] = [
            'name' => 'Basic Bath',
            'basePrice' => 300,
            'modifier' => getSizeModifier($petSize),
            'amount' => calculateServiceAmount(300, $petSize)
        ];
        
        $services[] = [
            'name' => 'Full Grooming',
            'basePrice' => 500,
            'modifier' => getSizeModifier($petSize),
            'amount' => calculateServiceAmount(500, $petSize)
        ];
        
        // Add nail trimming
        $services[] = [
            'name' => 'Nail Trimming',
            'basePrice' => 100,
            'modifier' => 'Standard',
            'amount' => 100
        ];
        
    } elseif ($petType === 'cat') {
        $services[] = [
            'name' => 'Cat Bath',
            'basePrice' => 250,
            'modifier' => getSizeModifier($petSize),
            'amount' => calculateServiceAmount(250, $petSize)
        ];
        
        $services[] = [
            'name' => 'Cat Grooming',
            'basePrice' => 400,
            'modifier' => getSizeModifier($petSize),
            'amount' => calculateServiceAmount(400, $petSize)
        ];
        
        // Add ear cleaning for cats
        $services[] = [
            'name' => 'Ear Cleaning',
            'basePrice' => 150,
            'modifier' => 'Standard',
            'amount' => 150
        ];
    } else {
        // Generic service for other pet types
        $services[] = [
            'name' => 'Basic Service',
            'basePrice' => 200,
            'modifier' => getSizeModifier($petSize),
            'amount' => calculateServiceAmount(200, $petSize)
        ];
    }
    
    // Adjust amounts to match total
    $calculatedTotal = array_sum(array_column($services, 'amount'));
    if ($calculatedTotal != $totalAmount) {
        // Add adjustment service to match the total
        $adjustment = $totalAmount - $calculatedTotal;
        if ($adjustment > 0) {
            $services[] = [
                'name' => 'Additional Services',
                'basePrice' => $adjustment,
                'modifier' => 'As needed',
                'amount' => $adjustment
            ];
        }
    }
    
    return $services;
}

function getSizeModifier($size) {
    switch ($size) {
        case 'small':
            return 'Small (-20%)';
        case 'medium':
            return 'Medium (Standard)';
        case 'large':
            return 'Large (+50%)';
        case 'xlarge':
            return 'Extra Large (+75%)';
        default:
            return 'Standard';
    }
}

function calculateServiceAmount($basePrice, $size) {
    switch ($size) {
        case 'small':
            return round($basePrice * 0.8);
        case 'medium':
            return $basePrice;
        case 'large':
            return round($basePrice * 1.5);
        case 'xlarge':
            return round($basePrice * 1.75);
        default:
            return $basePrice;
    }
}
?>
