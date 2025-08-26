<?php
// Test actual email sending
echo "Testing Email Sending...\n";

require_once __DIR__ . '/../vendor/autoload.php';

try {
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    
    // Enable debug output
    $mail->SMTPDebug = 2; // Enable verbose debug output
    
    echo "Setting up SMTP...\n";
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'animates.ph.fairview@gmail.com';
    $mail->Password   = 'azzpxhvpufmmaips';
    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    
    echo "Setting up email content...\n";
    $mail->setFrom('animates.ph.fairview@gmail.com', 'Animates PH - Camaro Branch');
    $mail->addAddress('animates.ph.fairview@gmail.com', 'Test Recipient'); // Send to yourself for testing
    
    $mail->isHTML(true);
    $mail->Subject = "Test Email - Animates PH";
    
    $emailBody = "<h2>Test Email</h2><p>This is a test email to verify the email system is working.</p>";
    $mail->Body = $emailBody;
    $mail->AltBody = "Test Email - This is a test email to verify the email system is working.";
    
    echo "Attempting to send email...\n";
    $result = $mail->send();
    
    if ($result) {
        echo "✅ Email sent successfully!\n";
    } else {
        echo "❌ Email sending failed!\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\nTest completed.\n";
?>
