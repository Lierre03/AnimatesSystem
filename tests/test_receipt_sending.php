<?php
// Test receipt sending process
echo "Testing Receipt Sending Process...\n";

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    if (!$db) {
        echo "❌ Database connection failed\n";
        exit;
    }
    
    echo "✅ Database connection successful\n";
    
    // Test with a real booking ID
    $bookingId = 37; // Use a booking that exists
    
    echo "Testing with booking ID: $bookingId\n";
    
    // Get booking details (same query as in sendPaymentReceipt)
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
        echo "❌ Booking not found for ID: $bookingId\n";
        exit;
    }
    
    echo "✅ Booking found: {$booking['pet_name']} ({$booking['pet_type']})\n";
    echo "Owner: {$booking['owner_name']} ({$booking['owner_email']})\n";
    
    if (!$booking['owner_email']) {
        echo "❌ No email address found for booking\n";
        exit;
    }
    
    // Now test the email sending part
    echo "Testing email sending...\n";
    
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    
    // Disable debug for cleaner output
    $mail->SMTPDebug = 0;
    
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
    $mail->Subject = "Test Receipt - Animates PH";
    
    $emailBody = "<h2>Test Receipt</h2><p>This is a test receipt for {$booking['pet_name']}.</p>";
    $mail->Body = $emailBody;
    $mail->AltBody = "Test Receipt for {$booking['pet_name']}";
    
    $result = $mail->send();
    
    if ($result) {
        echo "✅ Receipt email sent successfully!\n";
    } else {
        echo "❌ Receipt email sending failed!\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\nTest completed.\n";
?>
