<?php
// Test script for payment processing
echo "Testing Payment Processing...\n";

// Test data
$testData = [
    'action' => 'process_payment',
    'booking_id' => 35, // Use the booking ID from our previous test
    'payment_method' => 'cash',
    'payment_reference' => null,
    'payment_platform' => null,
    'send_receipt' => false
];

echo "Test Data: " . json_encode($testData, JSON_PRETTY_PRINT) . "\n\n";

// Make the API call
$url = 'http://localhost/animates/api/billing.php';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "HTTP Response Code: $httpCode\n";
echo "Response: $response\n";

if (curl_error($ch)) {
    echo "cURL Error: " . curl_error($ch) . "\n";
}

curl_close($ch);

echo "\nTest completed.\n";
?>
