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
    
    // Check if database connection is successful
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Handle POST request for processing payment
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (isset($data['action']) && $data['action'] === 'process_payment') {
            $bookingId = $data['booking_id'] ?? 0;
            $paymentMethod = $data['payment_method'] ?? '';
            $paymentReference = $data['payment_reference'] ?? null;
            $paymentPlatform = $data['payment_platform'] ?? null;
            $amountTendered = $data['amount_tendered'] ?? null;
            $changeAmount = $data['change_amount'] ?? null;
            $sendReceipt = $data['send_receipt'] ?? false;
            
            if (empty($bookingId) || empty($paymentMethod)) {
                echo json_encode(['success' => false, 'message' => 'Booking ID and payment method are required']);
                exit;
            }
            
            // Start transaction
            $db->beginTransaction();
            
            try {
                // Update booking status to completed and payment status to paid
                $stmt = $db->prepare("UPDATE bookings SET 
                    status = 'completed', 
                    payment_status = 'paid',
                    actual_completion = NOW(),
                    payment_method = ?,
                    payment_reference = ?,
                    payment_platform = ?,
                    amount_tendered = ?,
                    change_amount = ?,
                    payment_date = NOW()
                    WHERE id = ?");
                $stmt->execute([$paymentMethod, $paymentReference, $paymentPlatform, $amountTendered, $changeAmount, $bookingId]);
                
                // Reset RFID card is_currently_booked flag when payment is completed
                $stmt = $db->prepare("
                    UPDATE rfid_cards rc 
                    JOIN bookings b ON rc.id = b.rfid_card_id 
                    SET rc.is_currently_booked = 0 
                    WHERE b.id = ?
                ");
                $stmt->execute([$bookingId]);
                 
                             // Get the total amount and apply discount
             $stmt = $db->prepare("SELECT total_amount FROM bookings WHERE id = ?");
             $stmt->execute([$bookingId]);
             $booking = $stmt->fetch(PDO::FETCH_ASSOC);
             $totalAmount = $booking['total_amount'];
             
             // Get discount amount from the request data
             $discountAmount = floatval($data['discount_amount'] ?? 0);
             
             // Log the received data for debugging
             error_log("Payment data received - discount_amount: " . ($data['discount_amount'] ?? 'NOT SET'));
             error_log("Payment data received - full data: " . json_encode($data));
             
             // Calculate final amount after discount (tax is inclusive)
             $finalAmount = $totalAmount - $discountAmount;
             
             // Create sales transaction record with the FINAL amount after discount
             $transactionReference = 'TXN-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 8));
             $stmt = $db->prepare("INSERT INTO sales_transactions 
                 (booking_id, transaction_reference, amount, payment_method, payment_platform, discount_amount, status) 
                 VALUES (?, ?, ?, ?, ?, ?, 'completed')");
             $stmt->execute([$bookingId, $transactionReference, $finalAmount, $paymentMethod, $paymentPlatform, $discountAmount]);
             
             // Log the transaction details for debugging
             error_log("Sales transaction created - Booking ID: $bookingId, Original: $totalAmount, Discount: $discountAmount, Final: $finalAmount, Transaction Ref: $transactionReference");
                
                // Log the transaction details for debugging
                error_log("Payment processed - Booking ID: $bookingId, Original: $totalAmount, Discount: $discountAmount, Final: $finalAmount");
                
                // Commit transaction
                $db->commit();
                
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
                    'receipt_sent' => $receiptSent,
                    'transaction_reference' => $transactionReference
                ]);
                
            } catch (Exception $e) {
                // Rollback on error
                $db->rollBack();
                error_log("Error processing payment: " . $e->getMessage());
                echo json_encode([
                    'success' => false, 
                    'message' => 'Failed to process payment: ' . $e->getMessage()
                ]);
            }
            exit;
        }
    }
    
    // Handle GET request for printing receipt
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'print_receipt') {
        $bookingId = $_GET['booking_id'] ?? 0;
        $paymentMethod = $_GET['payment_method'] ?? '';
        $paymentReference = $_GET['payment_reference'] ?? null;
        $paymentPlatform = $_GET['payment_platform'] ?? null;
        $isEmbed = isset($_GET['embed']) && ($_GET['embed'] === '1' || $_GET['embed'] === 'true');
        
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
            b.payment_method,
            b.amount_tendered,
            b.change_amount,
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
        $paymentInfo = $booking['payment_method'] ?? $paymentMethod;
        if ($paymentInfo === 'online') {
                    $paymentInfo .= " ($paymentPlatform, Ref: $paymentReference)";
                }

                // Get discount amount from sales_transactions
                $stmt = $db->prepare("SELECT discount_amount FROM sales_transactions WHERE booking_id = ? ORDER BY id DESC LIMIT 1");
                $stmt->execute([$bookingId]);
                $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
                $discountAmount = $transaction ? floatval($transaction['discount_amount']) : 0;
                
                // Calculate final total (tax is inclusive, so no additional tax calculation)
                $subtotal = $booking['total_amount'];
                $finalTotal = $subtotal - $discountAmount;
                
                // Optional sections (hidden when embedded in modal)
                $thankYouSection = $isEmbed ? '' : "<div class=\"thank-you\">\n                   Thank you for choosing Animates PH!\n               </div>";
                $footerSection = $isEmbed ? '' : "<div class=\"footer\">\n                   <p>© 2025 Animates PH. All rights reserved.</p>\n               </div>";
                $printButtonSection = $isEmbed ? '' : "<div class=\"no-print\" style=\"text-align: center; margin-top: 30px;\">\n                   <button onclick=\"window.print()\" style=\"padding: 10px 20px; background: #D4AF37; color: white; border: none; border-radius: 5px; cursor: pointer;\">\n                       Print Receipt\n                   </button>\n               </div>";

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
                         " . ($discountAmount > 0 ? "
                         <tr>
                             <td><strong>Discount Applied:</strong></td>
                             <td>₱{$discountAmount}</td>
                         </tr>" : "") . "
                         " . ($booking['payment_method'] === 'cash' && $booking['amount_tendered'] > 0 ? "
                         <tr>
                             <td><strong>Amount Tendered:</strong></td>
                             <td>₱{$booking['amount_tendered']}</td>
                         </tr>
                         <tr>
                             <td><strong>Change:</strong></td>
                             <td>₱{$booking['change_amount']}</td>
                         </tr>" : "") . "
                    </table>
                </div>
                
                <div class=\"services\">
                    <h3>Services</h3>
                    {$booking['services']}
                </div>
                
                " . ($discountAmount > 0 ? "
                <div style='background-color: #f8fff8; border: 2px solid #28a745; border-radius: 8px; padding: 15px; margin: 20px 0; text-align: center;'>
                    <h3 style='color: #28a745; margin: 0 0 10px 0;'>🎉 Discount Applied!</h3>
                    <p style='font-size: 18px; margin: 0; color: #333;'>
                        You saved <strong>₱{$discountAmount}</strong> on this transaction!
                    </p>
                </div>" : "") . "
                
                                  <div class='total'>
                     <div style='margin-bottom: 10px;'>
                         <span>Subtotal: ₱{$subtotal}</span>
                     </div>
                     " . ($discountAmount > 0 ? "
                     <div style='margin-bottom: 10px; color: #28a745;'>
                         <span>Discount Applied: -₱{$discountAmount}</span>
                     </div>" : "") . "
                     <div style='font-size: 20px;'>
                         <span>Total: ₱{$finalTotal}</span>
                     </div>
                 </div>
                
                {$thankYouSection}
                {$footerSection}
                {$printButtonSection}
            </div>
        </body>
        </html>";
        exit;
    }
    
    // Handle regular GET request for billing information (only if no specific action)
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['action'])) {
        $rfid = $_GET['rfid'] ?? '';
        
        if (empty($rfid)) {
            echo json_encode(['success' => false, 'error' => 'RFID tag or booking ID is required']);
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
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['action'])) {
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
    
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

// Helper function to generate service breakdown based on pet type and size
function generateServiceBreakdown($petType, $petSize, $totalAmount) {
    $services = [];
    
    // Base services for different pet types (tax inclusive prices)
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
    
    // Calculate the total from services
    $calculatedTotal = array_sum(array_column($services, 'amount'));
    
    // If the calculated total doesn't match the booking total, adjust the services
    if ($calculatedTotal != $totalAmount) {
        // For small differences, adjust the first service
        if (abs($calculatedTotal - $totalAmount) <= 50) {
            $services[0]['amount'] += ($totalAmount - $calculatedTotal);
        } else {
            // For larger differences, create a proper service breakdown
            // Clear existing services and create a custom breakdown
            $services = [];
            
            // For cats, if total is around 200, it's likely just ear cleaning
            if ($petType === 'cat' && $totalAmount <= 200) {
                $services[] = [
                    'name' => 'Ear Cleaning',
                    'basePrice' => $totalAmount,
                    'modifier' => 'Standard',
                    'amount' => $totalAmount
                ];
            } else {
                // Create a custom service to match the total
                $services[] = [
                    'name' => 'Pet Grooming Service',
                    'basePrice' => $totalAmount,
                    'modifier' => 'Standard',
                    'amount' => $totalAmount
                ];
            }
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

// ===== TRANSACTION MANAGEMENT ENDPOINTS =====

// Handle GET request for fetching pending bills
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_pending_bills') {
    try {
        // Get pending bills with customer and pet information
        $stmt = $db->prepare("SELECT 
            b.id as booking_id,
            b.custom_rfid,
            b.total_amount,
            b.check_in_time,
            b.estimated_completion,
            b.actual_completion,
            b.status,
            b.payment_status,
            b.staff_notes,
            p.id as pet_id,
            p.name as pet_name,
            p.breed as pet_breed,
            p.type as pet_type,
            p.size as pet_size,
            c.id as customer_id,
            c.name as customer_name,
            c.phone as customer_phone,
            c.email as customer_email,
            GROUP_CONCAT(CONCAT(s.name, ' - ₱', s.price) SEPARATOR ', ') as services
        FROM bookings b
        JOIN pets p ON b.pet_id = p.id
        JOIN customers c ON p.customer_id = c.id
        LEFT JOIN booking_services bs ON b.id = bs.booking_id
        LEFT JOIN services s ON bs.service_id = s.id
        WHERE (b.payment_status IS NULL OR b.payment_status <> 'paid')
          AND b.status IN ('checked_in', 'in_progress', 'ready_for_pickup', 'completed')
          AND b.total_amount IS NOT NULL AND b.total_amount > 0
        GROUP BY b.id
        ORDER BY b.check_in_time ASC");
        
        $stmt->execute();
        $pendingBills = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($pendingBills === false) {
            throw new Exception('Failed to fetch pending bills data from database');
        }
        
        echo json_encode([
            'success' => true,
            'pending_bills' => $pendingBills
        ]);
        exit;
        
    } catch (Exception $e) {
        error_log("Error fetching pending bills: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Failed to fetch pending bills: ' . $e->getMessage()
        ]);
        exit;
    }
}

// Handle GET request for fetching transactions
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_transactions') {
    try {
        // Return only the latest transaction per booking (deduped by RFID/booking)
        $stmt = $db->prepare("SELECT 
            st.id,
            st.transaction_reference,
            st.amount,
            st.payment_method,
            st.payment_platform,
            st.status,
            st.created_at,
            b.id AS booking_id,
            b.custom_rfid AS rfid_tag,
            c.name AS customer_name,
            p.name AS pet_name,
            p.breed AS pet_breed
        FROM sales_transactions st
        INNER JOIN (
            SELECT booking_id, MAX(id) AS latest_id
            FROM sales_transactions
            GROUP BY booking_id
        ) latest ON latest.latest_id = st.id
        JOIN bookings b ON st.booking_id = b.id
        JOIN pets p ON b.pet_id = p.id
        JOIN customers c ON p.customer_id = c.id
        ORDER BY st.created_at DESC
        LIMIT 100");
        
        $stmt->execute();
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($transactions === false) {
            throw new Exception('Failed to fetch transaction data from database');
        }
        
        echo json_encode([
            'success' => true,
            'transactions' => $transactions
        ]);
        exit;
        
    } catch (Exception $e) {
        error_log("Error fetching transactions: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Failed to fetch transactions: ' . $e->getMessage()
        ]);
        exit;
    }
}

// Handle POST request for voiding transactions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'void_transaction') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $transactionId = $data['transaction_id'] ?? 0;
        $reason = $data['reason'] ?? '';
        $notes = $data['notes'] ?? '';
        
        if (empty($transactionId) || empty($reason)) {
            echo json_encode([
                'success' => false,
                'message' => 'Transaction ID and reason are required'
            ]);
            exit;
        }
        
        // Ensure audit table exists (best-effort)
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS `void_audit_log` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `transaction_id` INT(11) NOT NULL,
                `void_reason` VARCHAR(255) NOT NULL,
                `voided_by` INT(11) DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        } catch (Throwable $e) {
            // Ignore audit table creation failures; main void should still proceed
            error_log('void_audit_log create failed: ' . $e->getMessage());
        }

        // Start transaction
        $db->beginTransaction();
        
        try {
            // Update transaction status to voided
            $stmt = $db->prepare("UPDATE sales_transactions SET 
                status = 'voided',
                void_reason = ?,
                voided_at = NOW()
                WHERE id = ?");
            $stmt->execute([$reason, $transactionId]);
            
            // Log void action in audit table (best-effort)
            try {
                $stmt = $db->prepare("INSERT INTO void_audit_log (transaction_id, void_reason, voided_by) VALUES (?, ?, ?)");
                $stmt->execute([$transactionId, $reason, 1]); // Assuming user ID 1 for now
            } catch (Throwable $e) {
                error_log('void_audit_log insert failed: ' . $e->getMessage());
            }
            
            // Commit transaction
            $db->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Transaction voided successfully'
            ]);
            
        } catch (Exception $e) {
            // Rollback on error
            $db->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log("Error voiding transaction: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Failed to void transaction: ' . $e->getMessage()
        ]);
    }
    exit;
}

// Handle GET request for listing voided transactions
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_voided_transactions') {
    try {
        $stmt = $db->prepare("SELECT 
            st.id,
            st.transaction_reference,
            st.amount,
            st.payment_method,
            st.payment_platform,
            st.status,
            st.created_at,
            COALESCE(val.created_at, st.created_at) AS voided_at,
            COALESCE(val.void_reason, 'No reason provided') AS void_reason,
            b.id AS booking_id,
            b.custom_rfid AS rfid_tag,
            c.name AS customer_name,
            p.name AS pet_name,
            p.breed AS pet_breed
        FROM sales_transactions st
        JOIN bookings b ON st.booking_id = b.id
        JOIN pets p ON b.pet_id = p.id
        JOIN customers c ON p.customer_id = c.id
        LEFT JOIN void_audit_log val ON st.id = val.transaction_id
        WHERE st.status = 'voided'
        ORDER BY st.created_at DESC
        LIMIT 100");
        $stmt->execute();
        $voided = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'transactions' => $voided]);
        exit;
    } catch (Exception $e) {
        error_log("Error fetching voided transactions: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to fetch voided transactions: ' . $e->getMessage()]);
        exit;
    }
}

// Handle GET request to ensure void_audit_log table exists
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'ensure_void_audit_log') {
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS `void_audit_log` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `transaction_id` INT(11) NOT NULL,
            `void_reason` VARCHAR(255) NOT NULL,
            `voided_by` INT(11) DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        echo json_encode(['success' => true, 'message' => 'void_audit_log is ready']);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to create void_audit_log: ' . $e->getMessage()]);
    }
    exit;
}

// Handle POST request to restore a voided transaction back to completed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'restore_transaction') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $transactionId = $data['transaction_id'] ?? 0;
        if (empty($transactionId)) {
            echo json_encode(['success' => false, 'message' => 'Transaction ID is required']);
            exit;
        }
        $db->beginTransaction();
        try {
            // Restore status
            $stmt = $db->prepare("UPDATE sales_transactions SET status = 'completed' WHERE id = ?");
            $stmt->execute([$transactionId]);
            // Optional: remove audit log entry
            try {
                $stmt = $db->prepare("DELETE FROM void_audit_log WHERE transaction_id = ?");
                $stmt->execute([$transactionId]);
            } catch (Throwable $e) {
                error_log('void_audit_log delete failed: ' . $e->getMessage());
            }
            $db->commit();
            echo json_encode(['success' => true, 'message' => 'Transaction restored']);
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to restore transaction: ' . $e->getMessage()]);
    }
    exit;
}

// Handle GET request for exporting pending bills to CSV
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'export_pending_bills') {
    try {
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="pending_bills_' . date('Y-m-d_H-i-s') . '.csv"');
        
        // Get all pending bills with customer and pet information
        $stmt = $db->prepare("SELECT 
            b.custom_rfid,
            b.total_amount,
            b.check_in_time,
            b.estimated_completion,
            b.actual_completion,
            b.status,
            p.name as pet_name,
            p.breed as pet_breed,
            p.type as pet_type,
            c.name as customer_name,
            c.phone as customer_phone,
            c.email as customer_email
        FROM bookings b
        JOIN pets p ON b.pet_id = p.id
        JOIN customers c ON p.customer_id = c.id
        WHERE b.payment_status = 'pending' OR b.status IN ('in_progress', 'completed')
        ORDER BY b.check_in_time DESC");
        
        $stmt->execute();
        $pendingBills = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Create output stream
        $output = fopen('php://output', 'w');
        
        // Add UTF-8 BOM for proper Excel encoding
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Add CSV headers
        fputcsv($output, [
            'RFID Tag',
            'Customer Name',
            'Phone',
            'Email',
            'Pet Name',
            'Pet Breed',
            'Pet Type',
            'Total Amount',
            'Check-in Time',
            'Estimated Completion',
            'Actual Completion',
            'Status',
            'Payment Status'
        ]);
        
        // Add data rows
        foreach ($pendingBills as $bill) {
            $checkinTime = new DateTime($bill['check_in_time']);
            $estimatedTime = $bill['estimated_completion'] ? new DateTime($bill['estimated_completion']) : null;
            $actualTime = $bill['actual_completion'] ? new DateTime($bill['actual_completion']) : null;
            
            fputcsv($output, [
                $bill['custom_rfid'],
                $bill['customer_name'],
                $bill['customer_phone'] ?: 'N/A',
                $bill['customer_email'] ?: 'N/A',
                $bill['pet_name'],
                $bill['pet_breed'],
                ucfirst($bill['pet_type']),
                '₱' . number_format($bill['total_amount'], 2),
                $checkinTime->format('M j, Y g:i A'),
                $estimatedTime ? $estimatedTime->format('M j, Y g:i A') : 'TBD',
                $actualTime ? $actualTime->format('M j, Y g:i A') : 'N/A',
                ucfirst(str_replace('_', ' ', $bill['status'])),
                'Pending'
            ]);
        }
        
        fclose($output);
        exit;
        
    } catch (Exception $e) {
        error_log("Error exporting pending bills: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Failed to export pending bills: ' . $e->getMessage()
        ]);
        exit;
    }
}

// Handle GET request for exporting transactions to CSV
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'export_transactions') {
    try {
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="transactions_' . date('Y-m-d_H-i-s') . '.csv"');
        
        // Get only the latest transaction per booking (dedupe by booking/RFID)
        $stmt = $db->prepare("SELECT 
            st.transaction_reference,
            st.amount,
            st.payment_method,
            st.payment_platform,
            st.created_at,
            b.custom_rfid as rfid_tag,
            c.name as customer_name,
            p.name as pet_name,
            p.breed as pet_breed
        FROM sales_transactions st
        INNER JOIN (
            SELECT booking_id, MAX(id) AS latest_id
            FROM sales_transactions
            GROUP BY booking_id
        ) latest ON latest.latest_id = st.id
        JOIN bookings b ON st.booking_id = b.id
        JOIN pets p ON b.pet_id = p.id
        JOIN customers c ON p.customer_id = c.id
        ORDER BY st.created_at DESC");
        
        $stmt->execute();
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Create output stream
        $output = fopen('php://output', 'w');
        
        // Add UTF-8 BOM for proper Excel encoding
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Add CSV headers (omit status; completed transactions are already paid)
        fputcsv($output, [
            'Transaction Reference',
            'Date Time',
            'Customer Name',
            'Pet Name',
            'Pet Breed',
            'RFID Tag',
            'Amount',
            'Payment Method',
            'Payment Platform'
        ]);
        
        // Add data rows
        foreach ($transactions as $transaction) {
            $dateTime = new DateTime($transaction['created_at']);
            // Use ISO-like format for better spreadsheet recognition
            $formattedDate = $dateTime->format('Y-m-d H:i');
            // Amount as plain number for numeric cells
            $amountValue = number_format((float)$transaction['amount'], 2, '.', '');
            $platform = $transaction['payment_platform'] ?: '';
            fputcsv($output, [
                $transaction['transaction_reference'],
                $formattedDate,
                $transaction['customer_name'],
                $transaction['pet_name'],
                $transaction['pet_breed'],
                $transaction['rfid_tag'],
                $amountValue,
                ucfirst($transaction['payment_method']),
                $platform
            ]);
        }
        
        fclose($output);
        exit;
        
    } catch (Exception $e) {
        error_log("Error exporting transactions: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Failed to export transactions: ' . $e->getMessage()
        ]);
        exit;
    }
}

// Handle GET request for exporting transactions to Excel (auto-fit via HTML table)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'export_transactions_excel') {
    try {
        // Set headers for Excel (HTML table trick)
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="transactions_' . date('Y-m-d_H-i-s') . '.xls"');
        echo "\xEF\xBB\xBF"; // UTF-8 BOM

        // Get only the latest transaction per booking (dedupe by booking/RFID)
        $stmt = $db->prepare("SELECT 
            st.transaction_reference,
            st.amount,
            st.payment_method,
            st.payment_platform,
            st.created_at,
            b.custom_rfid as rfid_tag,
            c.name as customer_name,
            p.name as pet_name,
            p.breed as pet_breed
        FROM sales_transactions st
        INNER JOIN (
            SELECT booking_id, MAX(id) AS latest_id
            FROM sales_transactions
            GROUP BY booking_id
        ) latest ON latest.latest_id = st.id
        JOIN bookings b ON st.booking_id = b.id
        JOIN pets p ON b.pet_id = p.id
        JOIN customers c ON p.customer_id = c.id
        ORDER BY st.created_at DESC");

        $stmt->execute();
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Begin HTML table (Excel will auto-fit based on content)
        echo '<html><head><meta charset="utf-8"><style>table{border-collapse:collapse;} th,td{border:1px solid #ccc; padding:6px; font-family:Arial, sans-serif; font-size:11pt; white-space:nowrap;} th{text-align:left; background:#f5f5f5;}</style></head><body>';
        echo '<table>';
        echo '<thead><tr>'
            . '<th>Transaction Reference</th>'
            . '<th>Date Time</th>'
            . '<th>Customer Name</th>'
            . '<th>Pet Name</th>'
            . '<th>Pet Breed</th>'
            . '<th>RFID Tag</th>'
            . '<th>Amount</th>'
            . '<th>Payment Method</th>'
            . '<th>Payment Platform</th>'
            . '</tr></thead><tbody>';

        foreach ($transactions as $transaction) {
            $dateTime = new DateTime($transaction['created_at']);
            $formattedDate = $dateTime->format('Y-m-d H:i');
            $amountValue = number_format((float)$transaction['amount'], 2, '.', '');
            $platform = $transaction['payment_platform'] ?: '';
            echo '<tr>'
                . '<td>' . htmlspecialchars($transaction['transaction_reference']) . '</td>'
                . '<td>' . htmlspecialchars($formattedDate) . '</td>'
                . '<td>' . htmlspecialchars($transaction['customer_name']) . '</td>'
                . '<td>' . htmlspecialchars($transaction['pet_name']) . '</td>'
                . '<td>' . htmlspecialchars($transaction['pet_breed']) . '</td>'
                . '<td>' . htmlspecialchars($transaction['rfid_tag']) . '</td>'
                . '<td>' . htmlspecialchars($amountValue) . '</td>'
                . '<td>' . htmlspecialchars(ucfirst($transaction['payment_method'])) . '</td>'
                . '<td>' . htmlspecialchars($platform) . '</td>'
                . '</tr>';
        }

        echo '</tbody></table></body></html>';
        exit;

    } catch (Exception $e) {
        error_log("Error exporting transactions excel: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Failed to export transactions: ' . $e->getMessage()
        ]);
        exit;
    }
}

// Handle POST request for creating sales transactions (called when payment is processed)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'create_transaction') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $bookingId = $data['booking_id'] ?? 0;
        $amount = $data['amount'] ?? 0;
        $paymentMethod = $data['payment_method'] ?? '';
        $paymentPlatform = $data['payment_platform'] ?? null;
        
        if (empty($bookingId) || empty($amount) || empty($paymentMethod)) {
            echo json_encode([
                'success' => false,
                'message' => 'Booking ID, amount, and payment method are required'
            ]);
            exit;
        }
        
        // Generate transaction reference
        $transactionReference = 'TXN-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 8));
        
        // Insert transaction record
        $stmt = $db->prepare("INSERT INTO sales_transactions 
            (booking_id, transaction_reference, amount, payment_method, payment_platform, status) 
            VALUES (?, ?, ?, ?, ?, 'completed')");
        $stmt->execute([$bookingId, $transactionReference, $amount, $paymentMethod, $paymentPlatform]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Transaction created successfully',
            'transaction_reference' => $transactionReference
        ]);
        
    } catch (Exception $e) {
        error_log("Error creating transaction: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create transaction: ' . $e->getMessage()
        ]);
    }
    exit;
}
?>
