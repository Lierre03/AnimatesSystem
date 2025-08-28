<?php
require_once __DIR__ . '/email_functions.php';
require_once __DIR__ . '/../config/database.php';

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
            b.amount_tendered,
            b.change_amount,
            
            p.name as pet_name,
            p.type as pet_type,
            p.breed as pet_breed,
            c.name as owner_name,
            c.phone as owner_phone,
            c.email as owner_email,
            st.amount as sales_transaction_amount,
            COALESCE(st.discount_amount, 0) as sales_transaction_discount_amount
        FROM bookings b
        JOIN pets p ON b.pet_id = p.id
        JOIN customers c ON p.customer_id = c.id
        LEFT JOIN (
            SELECT 
                st1.booking_id,
                st1.amount,
                st1.discount_amount
            FROM sales_transactions st1
            INNER JOIN (
                SELECT booking_id, MAX(id) as max_id
                FROM sales_transactions
                GROUP BY booking_id
            ) st2 ON st1.booking_id = st2.booking_id AND st1.id = st2.max_id
        ) st ON b.id = st.booking_id
        WHERE b.id = ?");
        
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
        
        // Get services breakdown with detailed information
        $servicesStmt = $db->prepare("SELECT 
            s.name as service_name,
            s.price as base_price,
            CASE 
                WHEN p.type = 'cat' AND b.total_amount <= 200 THEN 'Ear Cleaning (Default)'
                ELSE 'Standard'
            END as modifier,
            CASE 
                WHEN p.type = 'cat' AND b.total_amount <= 200 THEN 200
                ELSE s.price
            END as amount
        FROM bookings b
        JOIN pets p ON b.pet_id = p.id
        LEFT JOIN booking_services bs ON b.id = bs.booking_id
        LEFT JOIN services s ON bs.service_id = s.id
        WHERE b.id = ?
        ORDER BY s.id");
        
        $servicesStmt->execute([$bookingId]);
        $services = $servicesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If no services found, create a default service entry
        if (empty($services)) {
            if ($booking['pet_type'] === 'cat' && $booking['total_amount'] <= 200) {
                $services = [[
                    'service_name' => 'Ear Cleaning',
                    'base_price' => 200,
                    'modifier' => 'Default',
                    'amount' => 200
                ]];
            } else {
                $services = [[
                    'service_name' => 'General Service',
                    'base_price' => $booking['total_amount'],
                    'modifier' => 'Standard',
                    'amount' => $booking['total_amount']
                ]];
            }
        }
        
        // Calculate subtotal and discount (tax is inclusive)
        $subtotal = $booking['total_amount'];
        $discount = floatval($booking['sales_transaction_discount_amount'] ?? 0);
        
        // Calculate final total after discount (tax is inclusive, so no additional tax)
        $finalTotal = $subtotal - $discount;
        
        // Debug logging
        error_log("Email Receipt Debug - Booking ID: $bookingId");
        error_log("Email Receipt Debug - Original Amount: $subtotal");
        error_log("Email Receipt Debug - Discount Amount: $discount");
        error_log("Email Receipt Debug - Final Total: $finalTotal");
        error_log("Email Receipt Debug - Sales Transaction Data: " . json_encode([
            'amount' => $booking['sales_transaction_amount'],
            'discount_amount' => $booking['sales_transaction_discount_amount']
        ]));
        
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
        
        // Build services table HTML
        $servicesHTML = '';
        foreach ($services as $service) {
            $servicesHTML .= "
                <tr>
                    <td>{$service['service_name']}</td>
                    <td>₱{$service['base_price']}</td>
                    <td>{$service['modifier']}</td>
                    <td>₱{$service['amount']}</td>
                </tr>";
        }
        
        // Build payment info HTML
        $paymentInfoHTML = "<tr><td><strong>Payment Method:</strong></td><td>$paymentMethod</td></tr>";
        if ($paymentReference) {
            $paymentInfoHTML .= "<tr><td><strong>Reference:</strong></td><td>$paymentReference</td></tr>";
        }
        if ($paymentPlatform) {
            $paymentInfoHTML .= "<tr><td><strong>Platform:</strong></td><td>$paymentPlatform</td></tr>";
        }
        if ($discount > 0) {
            $paymentInfoHTML .= "<tr style='background-color: #f8fff8;'><td><strong>Discount Applied:</strong></td><td style='color: #28a745; font-weight: bold;'>-₱{$discount}</td></tr>";
        }
        if ($booking['amount_tendered']) {
            $paymentInfoHTML .= "<tr><td><strong>Amount Tendered:</strong></td><td>₱{$booking['amount_tendered']}</td></tr>";
        }
        if ($booking['change_amount']) {
            $paymentInfoHTML .= "<tr><td><strong>Change:</strong></td><td>₱{$booking['change_amount']}</td></tr>";
        }

        // Build discount box HTML
        $discountBoxHTML = '';
        if ($discount > 0) {
            $discountBoxHTML = '
                <div style="background-color: #f8fff8; border: 2px solid #28a745; border-radius: 8px; padding: 15px; margin: 20px 0; text-align: center;">
                    <h3 style="color: #28a745; margin: 0 0 10px 0;">🎉 Discount Applied!</h3>
                    <p style="font-size: 18px; margin: 0; color: #333;">
                        You saved <strong>₱' . $discount . '</strong> on this transaction!
                    </p>
                </div>';
        }
        
        // Build discount row HTML for total section
        $discountRowHTML = '';
        if ($discount > 0) {
            $discountRowHTML = '
                    <div style="margin-bottom: 10px; color: #28a745;">
                        <span>Discount Applied: -₱' . $discount . '</span>
                    </div>';
        }
        
        $emailBody = '
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
                    max-width: 800px;
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
                .receipt-details {
                    margin-bottom: 20px;
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
                .services table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .services th, .services td {
                    padding: 8px;
                    text-align: left;
                    border-bottom: 1px solid #eee;
                }
                .services th {
                    background-color: #f8f9fa;
                    font-weight: bold;
                }
                .total {
                    font-size: 18px;
                    font-weight: bold;
                    text-align: right;
                    margin-top: 20px;
                    border-top: 2px solid #D4AF37;
                    padding-top: 10px;
                }
                .payment-info {
                    margin: 20px 0;
                    border-top: 1px solid #eee;
                    padding: 10px 0;
                }
                .payment-info table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .payment-info td {
                    padding: 8px;
                    border-bottom: 1px solid #eee;
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
                
                ' . $discountBoxHTML . '
                
                <div class="receipt-details">
                    <table>
                        <tr>
                            <td><strong>Receipt #:</strong></td>
                            <td>' . $receiptNumber . '</td>
                        </tr>
                        <tr>
                            <td><strong>Date:</strong></td>
                            <td>' . $formattedDate . '</td>
                        </tr>
                        <tr>
                            <td><strong>Time:</strong></td>
                            <td>' . $formattedTime . '</td>
                        </tr>
                        <tr>
                            <td><strong>Customer:</strong></td>
                            <td>' . $booking['owner_name'] . '</td>
                        </tr>
                        <tr>
                            <td><strong>Phone:</strong></td>
                            <td>' . $booking['owner_phone'] . '</td>
                        </tr>
                        <tr>
                            <td><strong>Pet:</strong></td>
                            <td>' . $booking['pet_name'] . ' (' . $booking['pet_type'] . ' - ' . $booking['pet_breed'] . ')</td>
                        </tr>
                        <tr>
                            <td><strong>RFID:</strong></td>
                            <td>' . $booking['custom_rfid'] . '</td>
                        </tr>
                        <tr>
                            <td><strong>Check-in Time:</strong></td>
                            <td>' . $formattedDate . ' ' . $formattedTime . '</td>
                        </tr>
                    </table>
                </div>
                
                <div class="services">
                    <h3>Services</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Base Price</th>
                                <th>Modifier</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            ' . $servicesHTML . '
                        </tbody>
                    </table>
                </div>
                
                <div class="total">
                    <div style="margin-bottom: 10px;">
                        <span>Subtotal: ₱' . $subtotal . '</span>
                    </div>
                    ' . $discountRowHTML . '
                    <div style="font-size: 20px;">
                        <span>Total: ₱' . $finalTotal . '</span>
                    </div>
                </div>
                
                <div class="payment-info">
                    <h3>Payment Details</h3>
                    <table>
                        ' . $paymentInfoHTML . '
                    </table>
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
        </html>';
        
        $mail->Body = $emailBody;
        $mail->AltBody = "Payment Receipt #$receiptNumber\n\n" .
                        "Date: $formattedDate\n" .
                        "Customer: {$booking['owner_name']}\n" .
                        "Pet: {$booking['pet_name']} ({$booking['pet_type']} - {$booking['pet_breed']})\n" .
                        "RFID: {$booking['custom_rfid']}\n" .
                        "Payment Method: $paymentInfo\n" .
                        "Subtotal: ₱{$subtotal}\n" .
                        ($discount > 0 ? "Discount: -₱{$discount}\n" : "") .
                        "Total: ₱{$finalTotal}\n\n" .
                        "Thank you for choosing Animates PH!";
        
        $mail->send();
        
        // Receipt sent successfully
        return true;
        
    } catch (Exception $e) {
        error_log("Receipt Email Error: " . $e->getMessage());
        return false;
    }
}