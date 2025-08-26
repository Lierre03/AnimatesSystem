<?php
// Test email configuration
echo "Testing Email Configuration...\n";

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
    
    echo "Testing SMTP connection...\n";
    
    // Test connection without sending
    if ($mail->smtpConnect()) {
        echo "✅ SMTP connection successful!\n";
        $mail->smtpClose();
    } else {
        echo "❌ SMTP connection failed!\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\nTest completed.\n";
?>
