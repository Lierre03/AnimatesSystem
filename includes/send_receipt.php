<?php
require_once __DIR__ . '/email_functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Send a payment receipt email to the customer
 * 
 * @param int $bookingId The booking ID
 * @param string $paymentMethod The payment method used
 * @param string $paymentReference The payment reference (for online payments)
 * @param string $paymentPlatform The payment platform (for online payments)
 * @return bool True if email sent successfully, false otherwise
 */
function sendPaymentReceipt($bookingId, $paymentMethod, $paymentReference = null, $paymentPlatform = null) {
    try {
        // Test SMTP configuration first
        if (!testEmailConfig()) {
            error_log("SMTP configuration test failed - proceeding anyway");
        }
        
        $db = getDB();
        if (!$db) {
            error_log("Failed to get database connection in sendPaymentReceipt");
            return false;
        }
        
        // Get booking details with all required information
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
            error_log("Booking not found for ID: $bookingId");
            return false;
        }
        
        if (!$booking['owner_email']) {
            error_log("No email address found for booking ID: $bookingId");
            return false;
        }
        
        // Format date
        $date = new DateTime($booking['check_in_time']);
        $formattedDate = $date->format('F j, Y');
        $formattedTime = $date->format('h:i A');
        
        // Generate receipt number
        $receiptNumber = 'RCPT-' . date('Ymd') . '-' . $booking['booking_id'];
        
        // Send email
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'animates.ph.fairview@gmail.com';
        $mail->Password   = 'azzpxhvpufmmaips';
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        $mail->setFrom('animates.ph.fairview@gmail.com', 'Animates PH - Camaro Branch');
        $mail->addAddress($booking['owner_email'], $booking['owner_name']);
        
        $mail->isHTML(true);
        $mail->Subject = "Payment Receipt - Animates PH - Receipt #$receiptNumber";
        
        // Create a nice HTML receipt
        $paymentInfo = $paymentMethod;
        if ($paymentMethod === 'online') {
            $paymentInfo .= " ($paymentPlatform, Ref: $paymentReference)";
        }
        
        $emailBody = <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                }
                .receipt {
                    max-width: 600px;
                    margin: 0 auto;
                    border: 1px solid #ddd;
                    padding: 20px;
                    border-radius: 5px;
                }
                .header {
                    text-align: center;
                    margin-bottom: 20px;
                    border-bottom: 2px solid #D4AF37;
                    padding-bottom: 10px;
                }
                .logo {
                    max-width: 150px;
                    height: auto;
                }
                .receipt-details {
                    margin-bottom: 20px;
                }
                .receipt-details table {
                    width: 100%;
                }
                .receipt-details td {
                    padding: 5px 0;
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
                .footer {
                    margin-top: 30px;
                    font-size: 12px;
                    text-align: center;
                    color: #777;
                }
                .thank-you {
                    text-align: center;
                    font-size: 18px;
                    margin: 30px 0;
                    color: #D4AF37;
                }
            </style>
        </head>
        <body>
            <div class="receipt">
                <div class="header">
                    <h2>Animates PH - Payment Receipt</h2>
                    <p>Camaro Branch</p>
                </div>
                
                <div class="receipt-details">
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
                
                <div class="services">
                    <h3>Services</h3>
                    {$booking['services']}
                </div>
                
                <div class="total">
                    Total: ₱{$booking['total_amount']}
                </div>
                
                <div class="thank-you">
                    Thank you for choosing Animates PH!
                </div>
                
                <div class="footer">
                    <p>This is an automatically generated receipt. For any questions, please contact us at animates.ph.fairview@gmail.com or call (02) 8123-4567.</p>
                    <p>© 2025 Animates PH. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        HTML;
        
        $mail->Body = $emailBody;
        $mail->AltBody = "Payment Receipt #$receiptNumber\n\n" .
                        "Date: $formattedDate\n" .
                        "Customer: {$booking['owner_name']}\n" .
                        "Pet: {$booking['pet_name']} ({$booking['pet_type']} - {$booking['pet_breed']})\n" .
                        "RFID: {$booking['custom_rfid']}\n" .
                        "Payment Method: $paymentInfo\n" .
                        "Total: ₱{$booking['total_amount']}\n\n" .
                        "Thank you for choosing Animates PH!";
        
        $mail->send();
        
        // Receipt sent successfully
        return true;
        
    } catch (Exception $e) {
        error_log("Receipt Email Error: " . $e->getMessage());
        return false;
    }
}